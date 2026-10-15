<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstadoSistemaResource;
use App\Models\DeviceCommand;
use App\Models\EstadoSistema;
use App\Models\EventoSeguridad;
use App\Models\IotDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SistemaEmergenciaController extends Controller
{
    public function aperturaEmergencia(Request $request): JsonResponse
    {
        Gate::authorize('operate', EstadoSistema::class);

        $idempotencyKey = $request->header('Idempotency-Key');
        abort_unless(is_string($idempotencyKey) && preg_match('/\A[a-zA-Z0-9._:-]{8,80}\z/', $idempotencyKey), 422, 'Se requiere una clave Idempotency-Key válida.');

        $command = DB::transaction(function () use ($request, $idempotencyKey): DeviceCommand {
            $device = IotDevice::query()
                ->where('name', 'esp8266')
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            abort_unless($device !== null, 503, 'El dispositivo ESP8266 no está aprovisionado.');

            $existing = DeviceCommand::query()
                ->where('iot_device_id', $device->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }

            $estado = EstadoSistema::query()->firstOrCreate([], [
                'esta_activado' => false,
                'puerta_abierta' => false,
                'movimiento_detectado' => false,
                'estado' => 'NORMAL',
                'modo_emergencia' => false,
                'servicio_puerta_activo' => true,
            ]);

            $command = DeviceCommand::query()->create([
                'id' => (string) Str::uuid(),
                'iot_device_id' => $device->id,
                'requested_by' => $request->user()->id,
                'action' => DeviceCommand::ACTION_OPEN_DOOR,
                'status' => DeviceCommand::STATUS_PENDING,
                'is_emergency' => true,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addSeconds(30),
            ]);

            $descripcion = 'Se solicitó apertura de emergencia; esperando confirmación electrónica del ESP8266.';
            $estado->forceFill([
                'modo_emergencia' => true,
                'estado' => 'ALARMA',
                'descripcion_ultima_alerta' => $descripcion,
                'fecha_ultima_alerta' => now(),
            ])->save();

            EventoSeguridad::query()->create([
                'tipo_evento' => 'APERTURA_EMERGENCIA',
                'descripcion' => $descripcion,
                'gravedad' => 'critico',
                'user_id' => $request->user()->id,
            ]);

            return $command;
        });

        return response()->json([
            'data' => [
                'id' => $command->id,
                'action' => $command->action,
                'status' => $command->status,
                'expires_at' => $command->expires_at->toISOString(),
                'claimed_at' => $command->claimed_at?->toISOString(),
                'confirmed_at' => $command->confirmed_at?->toISOString(),
                'result' => $command->result,
                'error' => $command->error,
                'message' => 'Solicitud aceptada; aún no hay confirmación del dispositivo.',
            ],
        ], 202);
    }

    public function toggleServicioPuerta(Request $request): JsonResponse
    {
        Gate::authorize('operate', EstadoSistema::class);

        $estado = DB::transaction(function () use ($request): EstadoSistema {
            $estado = EstadoSistema::query()->firstOrCreate([], [
                'esta_activado' => false,
                'puerta_abierta' => false,
                'movimiento_detectado' => false,
                'estado' => 'NORMAL',
                'modo_emergencia' => false,
                'servicio_puerta_activo' => true,
            ]);

            $serviceEnabled = ! $estado->servicio_puerta_activo;
            $estado->servicio_puerta_activo = $serviceEnabled;
            $estado->save();

            if (! $serviceEnabled) {
                DeviceCommand::query()
                    ->where('status', DeviceCommand::STATUS_PENDING)
                    ->where('is_emergency', false)
                    ->update([
                        'status' => DeviceCommand::STATUS_CANCELLED,
                        'updated_at' => now(),
                    ]);
            }

            EventoSeguridad::query()->create([
                'tipo_evento' => $serviceEnabled
                    ? 'SERVICIO_PUERTA_REACTIVADO'
                    : 'SERVICIO_PUERTA_DETENIDO',
                'descripcion' => $serviceEnabled
                    ? 'El servicio de puerta fue reactivado por personal autorizado.'
                    : 'El servicio de puerta fue detenido por personal autorizado.',
                'gravedad' => $serviceEnabled ? 'informativo' : 'advertencia',
                'user_id' => $request->user()->id,
            ]);

            return $estado->refresh();
        });

        return (new EstadoSistemaResource($estado))->response()->setStatusCode(200);
    }

    public function actualizarArmado(Request $request): JsonResponse
    {
        Gate::authorize('operate', EstadoSistema::class);

        $datos = $request->validate([
            'esta_activado' => ['required', 'boolean'],
        ]);

        $estado = DB::transaction(function () use ($datos, $request): EstadoSistema {
            $estado = EstadoSistema::query()->lockForUpdate()->firstOrCreate([], [
                'esta_activado' => false,
                'puerta_abierta' => false,
                'movimiento_detectado' => false,
                'estado' => 'NORMAL',
                'modo_emergencia' => false,
                'servicio_puerta_activo' => true,
            ]);

            $armed = (bool) $datos['esta_activado'];
            $wasArmed = $estado->esta_activado;
            $estado->esta_activado = $armed;
            if (! $armed && ! $estado->modo_emergencia) {
                $estado->estado = 'NORMAL';
            }
            $estado->save();

            if ($wasArmed !== $armed) {
                EventoSeguridad::query()->create([
                    'tipo_evento' => $armed ? 'ALARMA_ARMADA' : 'ALARMA_DESARMADA',
                    'descripcion' => $armed
                        ? 'La alarma fue armada por un administrador.'
                        : 'La alarma fue desarmada por un administrador.',
                    'gravedad' => 'informativo',
                    'user_id' => $request->user()->id,
                ]);
            }

            return $estado->refresh();
        });

        return (new EstadoSistemaResource($estado))->response()->setStatusCode(200);
    }
}
