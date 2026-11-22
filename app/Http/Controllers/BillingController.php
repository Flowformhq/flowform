<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ZonelessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingController extends Controller
{
    /**
     * Initiate Pro Tier upgrade or Pay-As-You-Go Credit Top-Up with Zoneless (USDT / USDC).
     */
    public function upgrade(Request $request, ZonelessService $zoneless): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('filament.admin.auth.login');
        }

        $pack = $request->query('pack');
        if ($pack) {
            $packUsd = max(1, (int) $pack);
            $checkoutUrl = $zoneless->createCreditCheckoutSession($user, $packUsd);

            if ($checkoutUrl) {
                return redirect()->away($checkoutUrl);
            }

            $credits = ZonelessService::getCreditsForPack($packUsd);
            return redirect('/admin')->with('status', "Topped up {$credits} credits (Test Mode Active).");
        }

        $interval = $request->query('interval', 'monthly');
        $checkoutUrl = $zoneless->createCheckoutSession($user, $interval);

        if ($checkoutUrl) {
            return redirect()->away($checkoutUrl);
        }

        // If no API key configured (demo / sandbox mode), simulate immediate upgrade
        return redirect('/admin')->with('status', 'Upgraded to FlowForm Pro (Test Mode Active).');
    }

    /**
     * Handle return from Zoneless payment.
     */
    public function success(Request $request): RedirectResponse
    {
        $type = $request->query('type');
        $msg = $type === 'credits'
            ? 'Payment successful! Your pay-as-you-go credits have been topped up.'
            : 'Payment successful! Your account has been upgraded to FlowForm Pro.';

        return redirect('/admin')->with('status', $msg);
    }

    /**
     * Handle cancelled checkout.
     */
    public function cancel(Request $request): RedirectResponse
    {
        return redirect('/admin')->with('status', 'Payment checkout was cancelled.');
    }

    /**
     * Handle incoming webhook from Zoneless for confirmed USDT / USDC payment.
     */
    public function webhook(Request $request, ZonelessService $zoneless): JsonResponse
    {
        $payload = $request->all();
        $success = $zoneless->handleWebhook($payload);

        return response()->json(['received' => true, 'processed' => $success], 200);
    }
}
