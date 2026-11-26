<?php

return [
    'version' => env('FLOWFORM_VERSION', '0.1.0'),

    'metering' => [
        'enabled' => (bool) env('FLOWFORM_METERING_ENABLED', false),
    ],

    'telemetry' => [
        'enabled' => (bool) env('FLOWFORM_TELEMETRY_ENABLED', false),

        'endpoint' => env(
            'FLOWFORM_TELEMETRY_ENDPOINT',
            'https://telemetry.flowformhq.com/api/v1/ping',
        ),

        'cron' => env('FLOWFORM_TELEMETRY_CRON', '0 0 * * *'),
    ],

    'webhooks' => [
        'allow_private_targets' => (bool) env('FLOWFORM_WEBHOOKS_ALLOW_PRIVATE_TARGETS', false),
    ],

    'uploads' => [
        'disk' => env('FLOWFORM_UPLOADS_DISK', 'local'),
        'max_kb' => (int) env('FLOWFORM_UPLOADS_MAX_KB', 10240),
        'max_files' => (int) env('FLOWFORM_UPLOADS_MAX_FILES', 5),
        'allowed_mimes' => array_filter(
            array_map(
                'trim',
                explode(',', env('FLOWFORM_UPLOADS_ALLOWED_MIMES', 'pdf,png,jpg,jpeg,gif,webp,txt,csv,doc,docx,xls,xlsx,zip'))
            )
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Redirect Hosts
    |--------------------------------------------------------------------------
    |
    | When public form submissions include _next or _redirect parameters,
    | redirects are strictly limited to relative paths or hosts in this allowlist
    | (or the host matching APP_URL). Supports wildcards like "*.example.com".
    |
    */
    'allowed_redirect_hosts' => array_filter(
        array_map(
            'trim',
            explode(',', env('FLOWFORM_ALLOWED_REDIRECT_HOSTS', 'localhost,127.0.0.1'))
        )
    ),
];
