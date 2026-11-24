<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\NotificationRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class VerifyNotificationRecipient extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public NotificationRecipient $recipient,
    ) {}

    public function envelope(): Envelope
    {
        $formName = $this->recipient->form->name;

        return new Envelope(
            subject: "Confirm Notification Email for {$formName}",
        );
    }

    public function content(): Content
    {
        $verificationUrl = URL::temporarySignedRoute(
            'notifications.verify',
            now()->addDays(7),
            ['recipient' => $this->recipient->id]
        );

        return new Content(
            markdown: 'emails.notifications.verify',
            with: [
                'recipient' => $this->recipient,
                'form' => $this->recipient->form,
                'verificationUrl' => $verificationUrl,
            ],
        );
    }
}
