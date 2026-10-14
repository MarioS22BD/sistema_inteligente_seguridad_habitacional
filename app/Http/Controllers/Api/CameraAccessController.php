<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\IpUtils;

class CameraAccessController extends Controller
{
    public function authorizeStream(Request $request): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user !== null, 401);
        abort_unless(in_array($user->rol, ['administrador', 'usuario'], true), 403);

        $ip = $request->ip();
        $ranges = config('camera.allowed_cidrs', []);

        abort_unless(
            $ip !== null && is_array($ranges) && $ranges !== [] && IpUtils::checkIp($ip, $ranges),
            403,
            'La transmisión solo está disponible desde la red local autorizada.',
        );

        return response('', 204);
    }
}
