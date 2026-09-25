<?php

use App\Http\Controllers\Api\SeguridadController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/seguridad/actualizar', [SeguridadController::class, 'actualizarEstado']);
