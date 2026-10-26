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

    /*
    |--------------------------------------------------------------------------
    | Allowed Redirect Hosts
    |--------------------------------------------------------------------------
    |
    | When public form submissions include _next or _redirect parameters,
    | redirects are strictly limited to relative paths or hosts in this allowlist
    | (or the host matching APP_URL). Supports wildcards like "*.buildeon.solutions".
    |
    */
    'allowed_redirect_hosts' => array_filter(
        array_map(
            'trim',
            explode(',', env('FLOWFORM_ALLOWED_REDIRECT_HOSTS', 'buildeon.solutions,*.buildeon.solutions,localhost,127.0.0.1'))
        )
    ),
];
