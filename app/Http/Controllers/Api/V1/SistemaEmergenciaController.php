<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstadoSistemaResource;
use App\Models\EstadoSistema;
use App\Models\EventoSeguridad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SistemaEmergenciaController extends Controller
{
    public function aperturaEmergencia(): EstadoSistemaResource
    {
        Gate::authorize('operate', EstadoSistema::class);

        $estado = DB::transaction(function (): EstadoSistema {
            $estado = EstadoSistema::query()->firstOrCreate([], [
                'esta_activado' => false,
                'puerta_abierta' => false,
                'movimiento_detectado' => false,
                'estado' => 'NORMAL',
                'modo_emergencia' => false,
                'servicio_puerta_activo' => true,
            ]);

            $descripcion = 'Apertura de emergencia solicitada por el personal autorizado.';

            $estado->forceFill([
                'puerta_abierta' => true,
                'modo_emergencia' => true,
                'servicio_puerta_activo' => true,
                'estado' => 'ALARMA',
                'descripcion_ultima_alerta' => $descripcion,
                'fecha_ultima_alerta' => now(),
            ])->save();

            EventoSeguridad::query()->create([
                'tipo_evento' => 'APERTURA_EMERGENCIA',
                'descripcion' => $descripcion,
                'gravedad' => 'critico',
                'user_id' => request()->user()->id,
            ]);

            return $estado->refresh();
        });

        return new EstadoSistemaResource($estado);
    }

    public function toggleServicioPuerta(): EstadoSistemaResource
    {
        Gate::authorize('operate', EstadoSistema::class);

        $estado = DB::transaction(function (): EstadoSistema {
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

            EventoSeguridad::query()->create([
                'tipo_evento' => $serviceEnabled
                    ? 'SERVICIO_PUERTA_REACTIVADO'
                    : 'SERVICIO_PUERTA_DETENIDO',
                'descripcion' => $serviceEnabled
                    ? 'El servicio de puerta fue reactivado por personal autorizado.'
                    : 'El servicio de puerta fue detenido por personal autorizado.',
                'gravedad' => $serviceEnabled ? 'informativo' : 'advertencia',
                'user_id' => request()->user()->id,
            ]);

            return $estado->refresh();
        });

        return new EstadoSistemaResource($estado);
    }
}
