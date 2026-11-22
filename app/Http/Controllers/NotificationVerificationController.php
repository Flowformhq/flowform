<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationRecipient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NotificationVerificationController extends Controller
{
    /**
     * Verify the notification recipient via signed URL.
     */
    public function verify(Request $request, NotificationRecipient $recipient): View
    {
        $recipient->markAsVerified();

        return view('notifications.verified', [
            'recipient' => $recipient,
            'form' => $recipient->form,
        ]);
    }
}
