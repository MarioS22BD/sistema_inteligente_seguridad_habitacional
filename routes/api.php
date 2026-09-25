<?php

use App\Http\Controllers\Api\EstadoSistemaController;
use App\Http\Controllers\Api\EventoSeguridadController;
use App\Http\Controllers\Api\SeguridadController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/seguridad/actualizar', [SeguridadController::class, 'actualizarEstado']);

Route::apiResource('v1/eventos', EventoSeguridadController::class);
Route::get('v1/estado-sistema', [EstadoSistemaController::class, 'show']);
