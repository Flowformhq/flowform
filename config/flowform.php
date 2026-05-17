<?php

return [
    'version' => env('FLOWFORM_VERSION', '0.1.0'),

    'telemetry' => [
        'enabled' => (bool) env('FLOWFORM_TELEMETRY_ENABLED', true),

        'endpoint' => env(
            'FLOWFORM_TELEMETRY_ENDPOINT',
            'https://telemetry.flowformhq.com/api/v1/ping',
        ),

        'cron' => env('FLOWFORM_TELEMETRY_CRON', '0 0 * * *'),
    ],
];
