<?php

use App\Http\Controllers\Api\EstadoSistemaController;
use App\Http\Controllers\Api\DeviceCommandController;
use App\Http\Controllers\Api\DeviceStatusController;
use App\Http\Controllers\Api\EventoSeguridadController;
use App\Http\Controllers\Api\SeguridadController;
use App\Http\Controllers\Api\V1\SistemaEmergenciaController;
use App\Http\Controllers\Api\V1\UserController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/dispositivos/esp8266')->middleware('device.token')->group(function (): void {
    Route::post('telemetria', [SeguridadController::class, 'reportarTelemetria']);
    Route::post('ordenes/reclamar', [DeviceCommandController::class, 'claim']);
    Route::post('ordenes/{command}/confirmar', [DeviceCommandController::class, 'confirm']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('v1/usuarios', UserController::class)->parameters([
        'usuarios' => 'usuario',
    ]);
    Route::apiResource('v1/eventos', EventoSeguridadController::class);
    Route::get('v1/estado-sistema', [EstadoSistemaController::class, 'show']);
    Route::get('v1/dispositivos/esp8266', [DeviceStatusController::class, 'esp8266']);
    Route::get('v1/sistema/ordenes', [DeviceCommandController::class, 'index']);
    Route::get('v1/sistema/ordenes/{command}', [DeviceCommandController::class, 'show']);
    Route::put('v1/sistema/armado', [SistemaEmergenciaController::class, 'actualizarArmado']);
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
