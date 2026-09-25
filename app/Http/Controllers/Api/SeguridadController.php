<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EstadoSistema;
use App\Models\EventoSeguridad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeguridadController extends Controller
{
    public function actualizarEstado(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'esta_activado' => ['nullable', 'boolean'],
            'puerta_abierta' => ['required', 'boolean'],
            'movimiento_detectado' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($datos): void {
            $estadoSistema = EstadoSistema::firstOrCreate([], [
                'esta_activado' => false,
                'puerta_abierta' => false,
                'movimiento_detectado' => false,
                'estado' => 'NORMAL',
                'descripcion_ultima_alerta' => null,
                'fecha_ultima_alerta' => null,
            ]);

            $puertaAnterior = $estadoSistema->puerta_abierta;
            $movimientoAnterior = $estadoSistema->movimiento_detectado;
            $estaActivadoAnterior = $estadoSistema->esta_activado;

            $estaActivado = array_key_exists('esta_activado', $datos)
                && $datos['esta_activado'] !== null
                ? (bool) $datos['esta_activado']
                : $estaActivadoAnterior;
            $puertaAbierta = (bool) $datos['puerta_abierta'];
            $movimientoDetectado = (bool) $datos['movimiento_detectado'];

            $nuevaApertura = ! $puertaAnterior && $puertaAbierta;
            $nuevoMovimiento = ! $movimientoAnterior && $movimientoDetectado;

            if ($nuevaApertura) {
                EventoSeguridad::create([
                    'tipo_evento' => 'PUERTA_ABIERTA',
                    'descripcion' => 'La puerta de la habitación fue abierta.',
                    'gravedad' => 'advertencia',
                ]);
            }

            if ($nuevoMovimiento) {
                EventoSeguridad::create([
                    'tipo_evento' => 'MOVIMIENTO_DETECTADO',
                    'descripcion' => 'Se detectó movimiento dentro de la habitación.',
                    'gravedad' => 'advertencia',
                ]);
            }

            $hayAlarma = $estaActivado && ($puertaAbierta || $movimientoDetectado);
            $nuevaCondicionAlarma = $hayAlarma && (
                $estadoSistema->estado !== 'ALARMA'
                || $nuevaApertura
                || $nuevoMovimiento
            );

            if ($hayAlarma) {
                $descripcionAlerta = match (true) {
                    $puertaAbierta && $movimientoDetectado => 'Se detectó apertura de puerta y movimiento mientras el sistema estaba activado.',
                    $puertaAbierta => 'Se detectó la apertura de la puerta mientras el sistema estaba activado.',
                    default => 'Se detectó movimiento mientras el sistema estaba activado.',
                };

                if ($nuevaCondicionAlarma) {
                    EventoSeguridad::create([
                        'tipo_evento' => 'ALARMA_DISPARADA',
                        'descripcion' => $descripcionAlerta,
                        'gravedad' => 'critico',
                    ]);
                }

                $estadoSistema->estado = 'ALARMA';
                $estadoSistema->descripcion_ultima_alerta = $descripcionAlerta;
                $estadoSistema->fecha_ultima_alerta = now();
            } else {
                $estadoSistema->estado = 'NORMAL';
            }

            $estadoSistema->esta_activado = $estaActivado;
            $estadoSistema->puerta_abierta = $puertaAbierta;
            $estadoSistema->movimiento_detectado = $movimientoDetectado;
            $estadoSistema->save();
        });

        return response()->json([
            'estado' => 'exito',
            'mensaje' => 'Estado actualizado correctamente',
        ], 200);
    }
}
