<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Zoneless API Configuration (Stablecoin Payments via USDT/USDC)
    |--------------------------------------------------------------------------
    |
    | Zoneless is a Stripe-compatible payment platform for stablecoins.
    | Set your secret key and optional self-hosted API URL.
    |
    */
    'api_url' => env('ZONELESS_API_URL', 'https://api.zoneless.com'),
    'secret_key' => env('ZONELESS_SECRET_KEY'),
    'webhook_secret' => env('ZONELESS_WEBHOOK_SECRET'),

    'pricing' => [
        'monthly' => (float) env('ZONELESS_PRO_PRICE_MONTHLY', 9.00),
        'annual' => (float) env('ZONELESS_PRO_PRICE_ANNUAL', 79.00),
    ],

    'accepted_currencies' => ['USDT', 'USDC'],
];
