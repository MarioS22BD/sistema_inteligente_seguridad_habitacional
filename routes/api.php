<?php

use App\Http\Controllers\Api\EstadoSistemaController;
use App\Http\Controllers\Api\EventoSeguridadController;
use App\Http\Controllers\Api\SeguridadController;
use App\Http\Controllers\Api\V1\SistemaEmergenciaController;
use App\Http\Controllers\Api\V1\UserController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/v1/seguridad/actualizar', [SeguridadController::class, 'actualizarEstado']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('v1/usuarios', UserController::class)->parameters([
        'usuarios' => 'usuario',
    ]);
    Route::apiResource('v1/eventos', EventoSeguridadController::class);
    Route::get('v1/estado-sistema', [EstadoSistemaController::class, 'show']);
    Route::post('v1/sistema/apertura-emergencia', [SistemaEmergenciaController::class, 'aperturaEmergencia']);
    Route::post('v1/sistema/toggle-servicio-puerta', [SistemaEmergenciaController::class, 'toggleServicioPuerta']);
    Route::get('v1/usuario', function (Request $request) {
        /** @var User $usuario */
        $usuario = $request->user();

        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,
            'rol' => $usuario->rol,
            'roles' => $usuario->getRoleNames(),
            'permisos' => $usuario->getAllPermissions()->pluck('name'),
        ];
    });
});
