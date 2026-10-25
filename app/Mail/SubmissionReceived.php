<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
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
        return new Envelope(
            subject: "New Submission: {$this->form->name}",
        );
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
}
