<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('alertas', 'Alertas')->name('alertas');
    Route::inertia('usuarios', 'Usuarios/Index')
        ->middleware('can:viewAny,App\\Models\\User')
        ->name('usuarios.index');
    Route::inertia('configuracion', 'Configuracion')->name('configuracion');
});

require __DIR__.'/settings.php';

Route::post('/login', [LoginController::class, 'login'])
    ->middleware(['guest:web', 'throttle:login'])
    ->name('login.store');

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth:web')
    ->name('logout');
