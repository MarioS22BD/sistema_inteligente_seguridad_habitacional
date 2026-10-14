<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
class VerifyDeviceToken
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tokenHeader = $request->header('X-Device-Token');
        $bearerToken = null;

        if ($request->hasHeader('Authorization')) {
            $authorization = trim((string) $request->header('Authorization'));
            if (str_starts_with(strtolower($authorization), 'bearer ')) {
                $bearerToken = trim(substr($authorization, 7));
            }
        }

        $suppliedToken = $tokenHeader ?: ($bearerToken ?: $request->query('token'));
        $expectedToken = (string) config('device.api_token', '');

        if ($expectedToken === '' || $suppliedToken === null || ! hash_equals($expectedToken, (string) $suppliedToken)) {
            abort(401, 'Token de dispositivo inválido o faltante.');
        }

        return $next($request);
    }
}
