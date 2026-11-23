<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Form;
use App\Models\MailSetting;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubmissionReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Form $form,
        public Submission $submission,
    ) {}

    public function envelope(): Envelope
    {
        $activeSetting = MailSetting::getActive();
        $from = null;

        if ($activeSetting && ! empty($activeSetting->from_address)) {
            $from = new Address(
                $activeSetting->from_address,
                $activeSetting->from_name ?: config('mail.from.name', 'FlowForm Engine')
            );
        }

        $replyTo = $this->resolveReplyTo();

        return new Envelope(
            from: $from,
            replyTo: $replyTo ? [$replyTo] : [],
            subject: "New Submission: {$this->form->name}",
        );
    }

    protected function resolveReplyTo(): ?string
    {
        $data = $this->submission->data;
        if (! is_array($data)) {
            return null;
        }

        foreach (['_replyto', 'reply_to', 'email'] as $key) {
            $candidate = $data[$key] ?? null;
            if (is_string($candidate)) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.submissions.received',
            with: [
                'form' => $this->form,
                'submission' => $this->submission,
                'data' => $this->submission->data ?? [],
            ],
        );
    }

    /**
     * @param  Factory|Mailer  $mailer
     */
    public function send($mailer)
    {
        $activeSetting = MailSetting::getActive();
        $activeSetting?->applyDynamicMailer('flowform');
        $this->mailer = $activeSetting ? 'flowform' : null;

        return parent::send($mailer);
    }
}
