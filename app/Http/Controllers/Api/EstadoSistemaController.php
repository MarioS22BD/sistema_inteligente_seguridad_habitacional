<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstadoSistemaResource;
use App\Models\EstadoSistema;

class EstadoSistemaController extends Controller
{
    public function show(): EstadoSistemaResource
    {
        return new EstadoSistemaResource(EstadoSistema::query()->firstOrFail());
    }
}
