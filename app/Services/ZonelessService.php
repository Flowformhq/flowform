<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZonelessService
{
    protected string $apiUrl;
    protected ?string $secretKey;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) config('zoneless.api_url', 'https://api.zoneless.com'), '/');
        $this->secretKey = config('zoneless.secret_key');
    }

    /**
     * Map USD pack amount to credit quantities.
     */
    public static function getCreditsForPack(int $usd): int
    {
        return match ($usd) {
            1 => 5000,
            5 => 28000,
            15 => 100000,
            45 => 350000,
            default => $usd * 5000,
        };
    }

    /**
     * Create a Zoneless Checkout Session for Pay-As-You-Go Credits (USDC/USDT).
     */
    public function createCreditCheckoutSession(User $user, int $packUsd = 1): ?string
    {
        $credits = self::getCreditsForPack($packUsd);

        if (empty($this->secretKey)) {
            Log::warning('Zoneless secret key is not configured. Falling back to simulated credit top-up.');
            $user->increment('credits_balance', $credits);
            return null;
        }

        $unitAmount = $packUsd * 100; // in cents

        $payload = [
            'mode' => 'payment',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => "FlowForm {$credits} Pay-As-You-Go Credits",
                            'description' => "Never-expiring credits: ~" . ($credits / 5) . " Amazon SES emails or ~{$credits} webhook/database submissions.",
                        ],
                        'unit_amount' => $unitAmount,
                    ],
                    'quantity' => 1,
                ],
            ],
            'client_reference_id' => (string) $user->id,
            'customer_email' => $user->email,
            'success_url' => url('/billing/success?session_id={CHECKOUT_SESSION_ID}&type=credits'),
            'cancel_url' => url('/billing/cancel'),
            'metadata' => [
                'user_id' => (string) $user->id,
                'type' => 'credits',
                'pack_usd' => (string) $packUsd,
                'credits' => (string) $credits,
                'payment_method' => 'usdt_usdc',
            ],
        ];

        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(15)
                ->post("{$this->apiUrl}/v1/checkout/sessions", $payload);

            if ($response->successful()) {
                $session = $response->json();
                return $session['url'] ?? null;
            }

            Log::error('Zoneless credit checkout creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Zoneless checkout exception', ['message' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Create a Zoneless Checkout Session for stablecoin payment (USDC/USDT).
     */
    public function createCheckoutSession(User $user, string $interval = 'monthly'): ?string
    {
        if (empty($this->secretKey)) {
            Log::warning('Zoneless secret key is not configured. Falling back to simulated upgrade.');
            // Allow simulated test upgrade in local / demo environment
            $user->update([
                'plan' => 'pro',
                'monthly_quota' => 10000,
                'subscription_status' => 'active',
                'subscription_expires_at' => now()->addMonth(),
            ]);
            return null;
        }

        $price = $interval === 'annual'
            ? (float) config('zoneless.pricing.annual', 79.00)
            : (float) config('zoneless.pricing.monthly', 9.00);

        $unitAmount = (int) round($price * 100);

        $payload = [
            'mode' => 'payment',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'FlowForm Pro (' . ucfirst($interval) . ')',
                            'description' => 'Unlimited forms, 10,000 submissions/mo, custom redirects & domain allowlists via USDT/USDC.',
                        ],
                        'unit_amount' => $unitAmount,
                    ],
                    'quantity' => 1,
                ],
            ],
            'client_reference_id' => (string) $user->id,
            'customer_email' => $user->email,
            'success_url' => url('/billing/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => url('/billing/cancel'),
            'metadata' => [
                'user_id' => (string) $user->id,
                'interval' => $interval,
                'payment_method' => 'usdt_usdc',
            ],
        ];

        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(15)
                ->post("{$this->apiUrl}/v1/checkout/sessions", $payload);

            if ($response->successful()) {
                $session = $response->json();
                return $session['url'] ?? null;
            }

            Log::error('Zoneless checkout creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Zoneless checkout exception', ['message' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Process webhook incoming from Zoneless upon confirmed stablecoin payment.
     */
    public function handleWebhook(array $payload): bool
    {
        $event = $payload['type'] ?? $payload['event'] ?? '';
        $data = $payload['data']['object'] ?? $payload['data'] ?? [];

        if ($event === 'checkout.session.completed') {
            $userId = $data['client_reference_id'] ?? $data['metadata']['user_id'] ?? null;
            if (! $userId) {
                Log::warning('Zoneless webhook received without client_reference_id', $payload);
                return false;
            }

            $user = User::find($userId);
            if (! $user) {
                Log::warning("Zoneless webhook user ID {$userId} not found");
                return false;
            }

            $type = $data['metadata']['type'] ?? 'subscription';

            if ($type === 'credits') {
                $credits = (int) ($data['metadata']['credits'] ?? 5000);
                $user->increment('credits_balance', $credits);
                Log::info("User #{$user->id} ({$user->email}) topped up {$credits} credits via Zoneless.");
                return true;
            }

            $interval = $data['metadata']['interval'] ?? 'monthly';
            $expiresAt = $interval === 'annual' ? now()->addYear() : now()->addMonth();

            $user->update([
                'plan' => 'pro',
                'monthly_quota' => 10000,
                'subscription_status' => 'active',
                'payment_reference_id' => $data['id'] ?? 'zl_' . bin2hex(random_bytes(6)),
                'subscription_expires_at' => $expiresAt,
            ]);

            Log::info("User #{$user->id} ({$user->email}) successfully upgraded to FlowForm Pro via Zoneless (USDT/USDC).");
            return true;
        }

        return true;
    }
}
