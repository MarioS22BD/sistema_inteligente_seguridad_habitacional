<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IotDevice;
use Illuminate\Http\JsonResponse;

class DeviceStatusController extends Controller
{
    public function esp8266(): JsonResponse
    {
        $device = IotDevice::query()->where('name', 'esp8266')->first();
        $lastSeen = $device?->last_seen_at;

        return response()->json([
            'data' => [
                'name' => 'esp8266',
                'configured' => $device !== null,
                'connected' => $device?->is_active === true
                    && $lastSeen?->greaterThan(now()->subSeconds(30)) === true,
                'last_seen_at' => $lastSeen?->toISOString(),
            ],
        ]);
    }
}
