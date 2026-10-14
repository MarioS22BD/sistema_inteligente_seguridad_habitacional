<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EstadoSistema;
use App\Models\EventoSeguridad;
use App\Models\IotDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeguridadController extends Controller
{
    public function reportarTelemetria(Request $request): JsonResponse
    {
        $device = $request->attributes->get('iot_device');
        abort_unless($device instanceof IotDevice && $device->name === 'esp8266', 403);

        $datos = $request->validate([
            'puerta_abierta' => ['required', 'boolean'],
            'movimiento_detectado' => ['required', 'boolean'],
            'distancia_cm' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        DB::transaction(function () use ($datos, $device): void {
            $estadoSistema = EstadoSistema::query()->lockForUpdate()->firstOrCreate([], [
                'esta_activado' => false,
                'puerta_abierta' => false,
                'movimiento_detectado' => false,
                'estado' => 'NORMAL',
                'descripcion_ultima_alerta' => null,
                'fecha_ultima_alerta' => null,
            ]);

            $puertaAnterior = $estadoSistema->puerta_abierta;
            $movimientoAnterior = $estadoSistema->movimiento_detectado;
            $puertaAbierta = (bool) $datos['puerta_abierta'];
            $movimientoDetectado = (bool) $datos['movimiento_detectado'];
            $nuevaApertura = ! $puertaAnterior && $puertaAbierta;
            $nuevoMovimiento = ! $movimientoAnterior && $movimientoDetectado;

            if ($nuevaApertura) {
                EventoSeguridad::query()->create([
                    'tipo_evento' => 'PUERTA_ABIERTA',
                    'descripcion' => 'El ESP8266 reportó la puerta abierta; no hay sensor de posición física.',
                    'gravedad' => 'advertencia',
                ]);
            }

            if ($nuevoMovimiento) {
                EventoSeguridad::query()->create([
                    'tipo_evento' => 'PROXIMIDAD_DETECTADA',
                    'descripcion' => 'El sensor HC-SR04 detectó proximidad.',
                    'gravedad' => 'advertencia',
                ]);
            }

            $hayAlarma = $estadoSistema->esta_activado && ($puertaAbierta || $movimientoDetectado);
            $nuevaCondicionAlarma = $hayAlarma && (
                $estadoSistema->estado !== 'ALARMA'
                || $nuevaApertura
                || $nuevoMovimiento
            );

            if ($hayAlarma) {
                $descripcionAlerta = match (true) {
                    $puertaAbierta && $movimientoDetectado => 'El ESP8266 reportó puerta abierta y proximidad mientras la alarma estaba armada.',
                    $puertaAbierta => 'El ESP8266 reportó la puerta abierta mientras la alarma estaba armada.',
                    default => 'El HC-SR04 detectó proximidad mientras la alarma estaba armada.',
                };

                if ($nuevaCondicionAlarma) {
                    EventoSeguridad::query()->create([
                        'tipo_evento' => 'ALARMA_DISPARADA',
                        'descripcion' => $descripcionAlerta,
                        'gravedad' => 'critico',
                    ]);
                }

                $estadoSistema->estado = 'ALARMA';
                $estadoSistema->descripcion_ultima_alerta = $descripcionAlerta;
                $estadoSistema->fecha_ultima_alerta = now();
            } elseif ($estadoSistema->modo_emergencia === false) {
                $estadoSistema->estado = 'NORMAL';
            }

            $estadoSistema->puerta_abierta = $puertaAbierta;
            $estadoSistema->movimiento_detectado = $movimientoDetectado;
            if (array_key_exists('distancia_cm', $datos)) {
                $estadoSistema->distancia_cm = $datos['distancia_cm'];
            }
            $estadoSistema->save();

            $device->forceFill(['last_seen_at' => now()])->save();
        });

        return response()->json([
            'estado' => 'exito',
            'mensaje' => 'Telemetría del ESP8266 actualizada.',
        ]);
    }
}
