<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceCommand;
use App\Models\EstadoSistema;
use App\Models\EventoSeguridad;
use App\Models\IotDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceCommandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('ver_estado_sistema'), 403);

        DB::transaction(function (): void {
            DeviceCommand::query()
                ->where('status', DeviceCommand::STATUS_PENDING)
                ->where('expires_at', '<=', now())
                ->update([
                    'status' => DeviceCommand::STATUS_EXPIRED,
                    'updated_at' => now(),
                ]);

            DeviceCommand::query()
                ->where('status', DeviceCommand::STATUS_PROCESSING)
                ->where('expires_at', '<=', now())
                ->update([
                    'status' => DeviceCommand::STATUS_FAILED,
                    'error' => 'Resultado incierto: expiró tras ser reclamada; no se reenviará automáticamente.',
                    'updated_at' => now(),
                ]);
        });

        $commands = DeviceCommand::query()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (DeviceCommand $command): array => $this->serialize($command));

        return response()->json(['data' => $commands]);
    }

    public function claim(Request $request): JsonResponse
    {
        $device = $this->deviceFromRequest($request);

        $command = DB::transaction(function () use ($device): ?DeviceCommand {
            $device->forceFill(['last_seen_at' => now()])->save();

            DeviceCommand::query()
                ->where('iot_device_id', $device->id)
                ->where('status', DeviceCommand::STATUS_PENDING)
                ->where('expires_at', '<=', now())
                ->update([
                    'status' => DeviceCommand::STATUS_EXPIRED,
                    'updated_at' => now(),
                ]);

            $command = DeviceCommand::query()
                ->where('iot_device_id', $device->id)
                ->where('status', DeviceCommand::STATUS_PENDING)
                ->where('expires_at', '>', now())
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if ($command === null) {
                return null;
            }

            $command->forceFill([
                'status' => DeviceCommand::STATUS_PROCESSING,
                'claimed_at' => now(),
            ])->save();

            return $command;
        });

        if ($command === null) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id' => $command->id,
                'action' => $command->action,
                'expires_at' => $command->expires_at->toISOString(),
            ],
        ]);
    }

    public function confirm(Request $request, DeviceCommand $command): JsonResponse
    {
        $device = $this->deviceFromRequest($request);
        abort_unless($command->iot_device_id === $device->id, 404);

        $data = $request->validate([
            'ejecutada' => ['required', 'boolean'],
            'puerta_abierta' => ['required', 'boolean'],
            'error' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = DB::transaction(function () use ($command, $data, $device): DeviceCommand {
            $locked = DeviceCommand::query()->lockForUpdate()->findOrFail($command->id);

            if (in_array($locked->status, [
                DeviceCommand::STATUS_EXECUTED,
                DeviceCommand::STATUS_FAILED,
            ], true)) {
                return $locked;
            }

            abort_unless($locked->status === DeviceCommand::STATUS_PROCESSING, 409, 'La orden no está en proceso.');
            abort_unless($locked->expires_at->isFuture(), 409, 'La orden expiró antes de confirmarse.');

            $success = (bool) $data['ejecutada'];
            $locked->forceFill([
                'status' => $success ? DeviceCommand::STATUS_EXECUTED : DeviceCommand::STATUS_FAILED,
                'confirmed_at' => now(),
                'result' => [
                    'servo_comandado' => $success,
                    'puerta_abierta_reportada' => (bool) $data['puerta_abierta'],
                    'confirmacion_fisica' => false,
                ],
                'error' => $success ? null : ($data['error'] ?? 'El dispositivo reportó fallo de ejecución.'),
            ])->save();

            if ($success) {
                EstadoSistema::query()->first()?->forceFill([
                    'puerta_abierta' => (bool) $data['puerta_abierta'],
                ])->save();
            }

            EventoSeguridad::query()->create([
                'tipo_evento' => $success ? 'ORDEN_PUERTA_EJECUTADA' : 'ORDEN_PUERTA_FALLIDA',
                'descripcion' => $success
                    ? 'El ESP8266 confirmó el comando del servo; la posición física no está verificada.'
                    : 'El ESP8266 no pudo confirmar la ejecución del comando del servo.',
                'gravedad' => $success ? 'informativo' : 'critico',
                'user_id' => $locked->requested_by,
            ]);

            $device->forceFill(['last_seen_at' => now()])->save();

            return $locked;
        });

        return response()->json(['data' => $this->serialize($updated)]);
    }

    public function show(Request $request, DeviceCommand $command): JsonResponse
    {
        abort_unless($request->user()->can('ver_estado_sistema'), 403);

        $this->expireIfNecessary($command);

        return response()->json(['data' => $this->serialize($command->refresh())]);
    }

    private function expireIfNecessary(DeviceCommand $command): void
    {
        if ($command->expires_at->isFuture()) {
            return;
        }

        if ($command->status === DeviceCommand::STATUS_PENDING) {
            $command->forceFill(['status' => DeviceCommand::STATUS_EXPIRED])->save();
        } elseif ($command->status === DeviceCommand::STATUS_PROCESSING) {
            $command->forceFill([
                'status' => DeviceCommand::STATUS_FAILED,
                'error' => 'Resultado incierto: expiró tras ser reclamada; no se reenviará automáticamente.',
            ])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DeviceCommand $command): array
    {
        return [
            'id' => $command->id,
            'action' => $command->action,
            'status' => $command->status,
            'expires_at' => $command->expires_at->toISOString(),
            'claimed_at' => $command->claimed_at?->toISOString(),
            'confirmed_at' => $command->confirmed_at?->toISOString(),
            'result' => $command->result,
            'error' => $command->error,
        ];
    }

    private function deviceFromRequest(Request $request): IotDevice
    {
        $device = $request->attributes->get('iot_device');
        abort_unless($device instanceof IotDevice && $device->name === 'esp8266', 403);

        return $device;
    }
}
