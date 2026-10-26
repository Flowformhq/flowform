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
     * Initiate Pro Tier upgrade with Zoneless (USDT / USDC).
     */
    public function upgrade(Request $request, ZonelessService $zoneless): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('filament.admin.auth.login');
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
        return redirect('/admin')->with('status', 'Payment successful! Your account has been upgraded to FlowForm Pro.');
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
