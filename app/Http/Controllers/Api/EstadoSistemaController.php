<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstadoSistemaResource;
use App\Models\EstadoSistema;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EstadoSistemaController extends Controller
{
    public function show(): JsonResponse
    {
        $estado = EstadoSistema::query()->firstOrCreate([], [
            'esta_activado' => false,
            'puerta_abierta' => false,
            'movimiento_detectado' => false,
            'estado' => 'NORMAL',
            'modo_emergencia' => false,
            'servicio_puerta_activo' => true,
        ]);

        Gate::authorize('view', $estado);

        return (new EstadoSistemaResource($estado))
            ->response()
            ->setStatusCode(200);
    }
}
