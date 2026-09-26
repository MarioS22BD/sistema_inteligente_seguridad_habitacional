<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LoginController extends Controller
{
    public function login(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $request->merge([
            'remember' => $request->boolean('recordar', $request->boolean('remember')),
        ]);

        try {
            return app(Pipeline::class)
                ->send($request)
                ->through(array_filter([
                    config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
                    config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
                    Features::enabled(Features::twoFactorAuthentication())
                        ? RedirectIfTwoFactorAuthenticatable::class
                        : null,
                    AttemptToAuthenticate::class,
                    PrepareAuthenticatedSession::class,
                ]))
                ->then(function (LoginRequest $request): JsonResponse|RedirectResponse {
                    if (! $request->expectsJson()) {
                        return redirect()->intended(config('fortify.home'));
                    }

                    $usuario = Auth::guard('web')->user();

                    return response()->json([
                        'mensaje' => 'Inicio de sesión exitoso',
                        'usuario' => [
                            'id' => $usuario->getAuthIdentifier(),
                            'name' => $usuario->name,
                            'email' => $usuario->email,
                            'rol' => $usuario->rol,
                        ],
                    ], 200);
                });
        } catch (ValidationException $exception) {
            if (! $request->expectsJson()) {
                throw $exception;
            }

            return response()->json([
                'mensaje' => 'Las credenciales proporcionadas no son correctas.',
            ], 422);
        }
    }

    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (! $request->expectsJson()) {
            return redirect()->route('home');
        }

        return response()->json([
            'mensaje' => 'Sesión cerrada correctamente',
        ], 200);
    }
}
