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
        // 1. Resolve form by access_key, slug, or uuid
        $form = Form::where('access_key', $slug)
            ->orWhere('slug', $slug)
            ->orWhere('uuid', $slug)
            ->active()
            ->first();

        if (! $form || ! $form->isEndpoint()) {
            return response()->json([
                'success' => false,
                'error' => 'Form endpoint not found or inactive.',
            ], 404)->header('Access-Control-Allow-Origin', '*');
        }

        // 2. Check Allowed Domain / Origin
        $origin = $request->header('origin') ?: $request->header('referer');
        if (! $form->isAllowedOrigin($origin)) {
            return response()->json([
                'success' => false,
                'error' => 'Submissions are not permitted from this domain origin.',
            ], 403)->header('Access-Control-Allow-Origin', '*');
        }

        // 3. Honeypot spam check: silently discard spam submissions
        $isSpam = ! empty($request->input('_gotcha')) || ! empty($request->input('_honeypot'));

        if ($isSpam) {
            return $this->respondSuccess($request, $form, null);
        }

        // 4. Quota & Pay-As-You-Go Credits Check for Form Owner
        $hasEmail = ! empty($form->notification_email);
        if ($form->user && ! $form->user->canReceiveSubmissions($hasEmail)) {
            return response()->json([
                'success' => false,
                'error' => 'Submission quota or credits exhausted for this form endpoint. Top up pay-as-you-go credits for as low as $1 at https://flowformhq.com.',
            ], 429)->header('Access-Control-Allow-Origin', '*');
        }

        // 5. Clean and Store Payload (with Field-Level AES-256 Encryption if enabled)
        $reservedKeys = ['_gotcha', '_honeypot', '_next', '_redirect', '_token', '_method'];
        $data = collect($request->all())->except($reservedKeys)->all();

        $submission = new Submission([
            'form_id' => $form->id,
            'status' => 'completed',
            'is_encrypted' => (bool) $form->is_encrypted,
            'current_step' => 1,
            'data' => $data,
            'meta' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
            ],
        ]);
        $submission->save();

        // 6. Dispatch Notification Email (Amazon SES Relay / Custom In-App SMTP)
        if ($hasEmail) {
            $recipients = array_filter(array_map('trim', explode(',', $form->notification_email)));
            if (! empty($recipients)) {
                try {
                    \App\Models\MailSetting::applyActiveConfig();
                    Mail::to($recipients)->send(new SubmissionReceived($form, $submission));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        // 7. Dispatch Outbound Webhook (with HMAC SHA-256 signature and exponential retries)
        if (! empty($form->webhook_url)) {
            try {
                \App\Jobs\DispatchFormWebhook::dispatch($form, $submission, $data);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // 8. Deduct Metred Pay-As-You-Go Credits (1 credit for webhook/DB, 5 credits for email relay)
        $form->user?->deductCredits($hasEmail);

        return $this->respondSuccess($request, $form, $submission);
    }

    protected function respondSuccess(Request $request, Form $form, ?Submission $submission): JsonResponse|RedirectResponse
    {
        $candidateUrl = $request->input('_next') ?: $request->input('_redirect');
        $redirectUrl = null;

        if ($candidateUrl && $this->isAllowedRedirectUrl($candidateUrl)) {
            $redirectUrl = $candidateUrl;
        } elseif ($form->redirect_url) {
            $redirectUrl = $form->redirect_url;
        }

        // If client explicitly expects JSON or is accessing the api/ prefix route
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Submission received',
                'id' => $submission?->uuid,
            ], 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With');
        }

        if ($redirectUrl) {
            return redirect()->away($redirectUrl);
        }

        return response()->json([
            'success' => true,
            'message' => 'Submission received',
            'id' => $submission?->uuid,
        ], 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With');
    }

    /**
     * Validate whether a redirect target is safe against open-redirect attacks.
     */
    protected function isAllowedRedirectUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        // Safe relative internal path: starts with '/', but not '//' or '/\'
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_starts_with($url, '/\\')) {
            return true;
        }

        $parsed = parse_url($url);
        if (! is_array($parsed) || empty($parsed['host'])) {
            return false;
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parsed['host']);

        // Check against APP_URL host
        $appUrl = config('app.url');
        if ($appUrl) {
            $appHost = parse_url((string) $appUrl, PHP_URL_HOST);
            if ($appHost && strtolower($appHost) === $host) {
                return true;
            }
        }

        // Check configured allowed hosts
        $allowedHosts = (array) config('flowform.allowed_redirect_hosts', []);
        foreach ($allowedHosts as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern === '') {
                continue;
            }

            if ($host === $pattern) {
                return true;
            }

            // Wildcard matching e.g. *.buildeon.solutions
            if (str_starts_with($pattern, '*.')) {
                $rootDomain = substr($pattern, 2);
                if ($host === $rootDomain || str_ends_with($host, '.' . $rootDomain)) {
                    return true;
                }
            }
        }

        return false;
    }
}
