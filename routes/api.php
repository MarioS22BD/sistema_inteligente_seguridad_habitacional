<?php

use App\Http\Controllers\Api\EstadoSistemaController;
use App\Http\Controllers\Api\EventoSeguridadController;
use App\Http\Controllers\Api\SeguridadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/v1/seguridad/actualizar', [SeguridadController::class, 'actualizarEstado']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('v1/eventos', EventoSeguridadController::class);
    Route::get('v1/estado-sistema', [EstadoSistemaController::class, 'show']);
    Route::get('v1/usuario', function (Request $request) {
        return $request->user()->only(['id', 'name', 'email', 'rol']);
    });
});
