<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationRecipient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NotificationVerificationController extends Controller
{
    /**
     * Show a confirmation step so link scanners that prefetch GET URLs cannot verify on the recipient's behalf.
     */
    public function show(Request $request, NotificationRecipient $recipient): View
    {
        return view('notifications.verified', [
            'recipient' => $recipient,
            'form' => $recipient->form,
            'confirmed' => $recipient->isVerified(),
            'confirmUrl' => $request->fullUrl(),
        ]);
    }

    public function verify(NotificationRecipient $recipient): View
    {
        $recipient->markAsVerified();

        return view('notifications.verified', [
            'recipient' => $recipient,
            'form' => $recipient->form,
            'confirmed' => true,
            'confirmUrl' => null,
        ]);
    }
}
