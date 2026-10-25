<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SubmissionReceived;
use App\Models\Form;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PublicEndpointController extends Controller
{
    /**
     * Handle public endpoint form submission.
     */
    public function submit(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $form = Form::where('slug', $slug)->active()->first();

        if (! $form || ! $form->isEndpoint()) {
            abort(404, 'Endpoint form not found or inactive.');
        }

        // Honeypot spam check: silently discard spam submissions
        $isSpam = ! empty($request->input('_gotcha')) || ! empty($request->input('_honeypot'));

        if ($isSpam) {
            return $this->respondSuccess($request, $form, null);
        }

        $reservedKeys = ['_gotcha', '_honeypot', '_next', '_redirect', '_token', '_method'];
        $data = collect($request->all())->except($reservedKeys)->all();

        $submission = Submission::create([
            'form_id' => $form->id,
            'status' => 'completed',
            'current_step' => 1,
            'data' => $data,
            'meta' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
            ],
        ]);

        if (! empty($form->notification_email)) {
            $recipients = array_filter(array_map('trim', explode(',', $form->notification_email)));
            if (! empty($recipients)) {
                Mail::to($recipients)->send(new SubmissionReceived($form, $submission));
            }
        }

        return $this->respondSuccess($request, $form, $submission);
    }

    protected function respondSuccess(Request $request, Form $form, ?Submission $submission): JsonResponse|RedirectResponse
    {
        $redirectUrl = $request->input('_next')
            ?: $request->input('_redirect')
            ?: $form->redirect_url;

        // If client explicitly expects JSON or is accessing the api/ prefix route
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Submission received',
                'id' => $submission?->uuid,
            ], 200);
        }

        if ($redirectUrl) {
            return redirect()->away($redirectUrl);
        }

        return response()->json([
            'success' => true,
            'message' => 'Submission received',
            'id' => $submission?->uuid,
        ], 200);
    }
}
