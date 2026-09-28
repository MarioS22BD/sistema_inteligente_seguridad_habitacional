<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstadoSistemaResource;
use App\Models\EstadoSistema;
use Illuminate\Support\Facades\Gate;

class EstadoSistemaController extends Controller
{
    public function show(): EstadoSistemaResource
    {
        $estado = EstadoSistema::query()->firstOrFail();

        Gate::authorize('view', $estado);

        return new EstadoSistemaResource($estado);
    }
}
