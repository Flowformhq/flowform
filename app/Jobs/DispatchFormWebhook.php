<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Form;
use App\Models\Submission;

class DispatchFormWebhook extends DeliverWebhook
{
    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function __construct(
        Form $form,
        Submission $submission,
        array $payload = [],
    ) {
        parent::__construct($form, $submission, $payload, null, 'form.submission');
    }
}
