<?php

return [
    'allowed_cidrs' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CAMERA_ALLOWED_CIDRS', '')),
    ))),
];
