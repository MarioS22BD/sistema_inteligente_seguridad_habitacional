<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\IpUtils;

class CameraAccessController extends Controller
{
    public function authorizeStream(Request $request): Response
    {
        $this->validateCameraAccess($request);

        return response('', 204);
    }

    public function health(Request $request): JsonResponse
    {
        $this->validateCameraAccess($request);

        $host = (string) config('camera.camera_host', '192.168.1.60');
        $port = (int) config('camera.camera_http_port', 80);

        $timeout = 1.5;
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (is_resource($socket)) {
            fclose($socket);

            return response()->json([
                'status' => 'connected',
                'camera_host' => $host,
                'camera_port' => $port,
            ], 200);
        }

        return response()->json([
            'status' => 'disconnected',
            'camera_host' => $host,
            'camera_port' => $port,
            'message' => 'Cámara no accesible en red local: '.$errstr,
        ], 503);
    }

    public function stream(Request $request): Response
    {
        $this->validateCameraAccess($request);

        // php artisan serve y los servidores de desarrollo PHP tienen workers limitados.
        // Transmitir MJPEG continuamente desde PHP bloquearía indefinidamente el proceso
        // e impediría atender solicitudes críticas (alarmas, telemetría y dashboard).
        // El streaming continuo en desarrollo debe ser atendido mediante el proxy inverso local
        // (Nginx para Windows) configurado según deploy/nginx/nginx-windows.conf.
        return response(
            "El streaming MJPEG continuo requiere el proxy inverso local (Nginx para Windows) para no agotar los procesos de PHP en desarrollo.\n"
            ."Consulte la configuración en deploy/nginx/nginx-windows.conf.",
            503,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Retry-After' => '5',
            ]
        );
    }

    private function validateCameraAccess(Request $request): void
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
    }
}
