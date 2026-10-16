<?php

return [
    'allowed_cidrs' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CAMERA_ALLOWED_CIDRS', '127.0.0.1/32,::1/128,192.168.137.0/24')),
    ))),
    'camera_host' => env('CAMERA_HOST', '192.168.137.28'),
    'camera_http_port' => (int) env('CAMERA_HTTP_PORT', 80),
    'camera_stream_port' => (int) env('CAMERA_STREAM_PORT', 81),
    'camera_proxy_token' => env('CAMERA_PROXY_TOKEN', 'sish-camera-live-2026-secret-9f2e'),
];
