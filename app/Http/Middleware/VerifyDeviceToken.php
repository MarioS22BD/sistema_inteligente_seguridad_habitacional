<?php

namespace App\Http\Middleware;

use App\Models\IotDevice;
use Closure;
use Illuminate\Http\Request;

class VerifyDeviceToken
{
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->header('X-Device-Token');

        if (! is_string($token) || $token === '') {
            abort(401, 'Credencial de dispositivo ausente o inválida.');
        }

        $tokenHash = hash('sha256', $token);
        $device = IotDevice::query()
            ->where('api_token_hash', $tokenHash)
            ->where('is_active', true)
            ->first();

        if (! $device || ! hash_equals($device->api_token_hash, $tokenHash)) {
            abort(401, 'Credencial de dispositivo ausente o inválida.');
        }

        $request->attributes->set('iot_device', $device);

        return $next($request);
    }
}
