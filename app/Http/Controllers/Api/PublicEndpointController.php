<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchFormWebhook;
use App\Jobs\SendChatNotification;
use App\Mail\SubmissionReceived;
use App\Models\Form;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

        // 2. Endpoint Server-to-Server Secret Check (skips origin + captcha when valid)
        $rawSecret = $request->header('X-FlowForm-Secret') ?? $request->query('_secret');
        $hasValidSecret = false;

        if ($rawSecret !== null && $rawSecret !== '') {
            if (! is_string($rawSecret) || ! $form->verifyEndpointSecret($rawSecret)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid endpoint secret.',
                ], 403)->header('Access-Control-Allow-Origin', '*');
            }

            $hasValidSecret = true;
        } elseif ($form->endpoint_secret_required) {
            return response()->json([
                'success' => false,
                'error' => 'Endpoint secret is required.',
            ], 403)->header('Access-Control-Allow-Origin', '*');
        }

        // 2b. Check Allowed Domain / Origin
        if (! $hasValidSecret) {
            $origin = $request->header('origin') ?: $request->header('referer');
            if (! $form->isAllowedOrigin($origin)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Submissions are not permitted from this domain origin.',
                ], 403)->header('Access-Control-Allow-Origin', '*');
            }
        }

        // 3. Honeypot spam check: silently discard spam submissions
        $isSpam = ! empty($request->input('_gotcha')) || ! empty($request->input('_honeypot'));

        if ($isSpam) {
            return $this->respondSuccess($request, $form, null);
        }

        // 3b. Server-Side Captcha Verification (Cloudflare Turnstile, hCaptcha)
        if (! $hasValidSecret && $form->hasCaptcha()) {
            $token = match ($form->captcha_provider) {
                'turnstile' => $request->input('cf-turnstile-response')
                    ?: $request->input('_turnstile')
                    ?: $request->input('g-recaptcha-response'),
                'hcaptcha' => $request->input('h-captcha-response')
                    ?: $request->input('_hcaptcha'),
                default => null,
            };

            if (empty($token)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Captcha verification token missing.',
                ], 422)->header('Access-Control-Allow-Origin', '*');
            }

            if (! $form->verifyCaptcha($token, $request->ip())) {
                return response()->json([
                    'success' => false,
                    'error' => 'Captcha verification failed.',
                ], 422)->header('Access-Control-Allow-Origin', '*');
            }
        }

        // 4. Quota & Pay-As-You-Go Credits Check for Form Owner
        $hasEmail = ! empty($form->notification_email);
        if (config('flowform.metering.enabled', false)) {
            if ($form->user && ! $form->user->canReceiveSubmissions($hasEmail)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Submission quota or credits exhausted for this form endpoint. Top up pay-as-you-go credits for as low as $1 at https://flowformhq.com.',
                ], 429)->header('Access-Control-Allow-Origin', '*');
            }
        }

        $files = $request->allFiles();
        $fileCount = count(Arr::flatten($files));

        if ($fileCount > 0) {
            $maxFiles = (int) config('flowform.uploads.max_files');
            if ($fileCount > $maxFiles) {
                return response()->json([
                    'success' => false,
                    'error' => "The maximum number of allowed files is {$maxFiles}.",
                    'errors' => ['files' => ["The maximum number of allowed files is {$maxFiles}."]],
                ], 422)->header('Access-Control-Allow-Origin', '*');
            }

            $allowed = implode(',', (array) config('flowform.uploads.allowed_mimes'));
            $fileRules = ['file', 'max:'.(int) config('flowform.uploads.max_kb'), 'mimes:'.$allowed, 'extensions:'.$allowed];
            $validator = Validator::make($files, collect($files)
                ->mapWithKeys(fn ($value, $field) => [(is_array($value) ? "{$field}.*" : $field) => $fileRules])
                ->all());

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'File upload validation failed.',
                    'errors' => $validator->errors()->toArray(),
                ], 422)->header('Access-Control-Allow-Origin', '*');
            }
        }

        $submissionUuid = Str::uuid()->toString();
        $uploadedFilesData = collect($files)
            ->map(fn ($value) => is_array($value)
                ? array_map(fn (UploadedFile $file) => $this->storeUpload($file, $submissionUuid), $value)
                : $this->storeUpload($value, $submissionUuid))
            ->all();

        // 5. Clean and Store Payload (with Field-Level AES-256 Encryption if enabled)
        $reservedKeys = [
            '_gotcha',
            '_honeypot',
            '_next',
            '_redirect',
            '_token',
            '_method',
            '_secret',
            '_source',
            'cf-turnstile-response',
            '_turnstile',
            'g-recaptcha-response',
            'h-captcha-response',
            '_hcaptcha',
        ];
        $data = collect($request->all())
            ->except($reservedKeys)
            ->merge($uploadedFilesData)
            ->all();

        $meta = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
        ];

        $rawSource = $request->query('_source') ?? $request->input('_source');
        if (is_string($rawSource) && preg_match('/^[a-z0-9-]{1,64}$/', $rawSource) === 1) {
            $meta['source'] = $rawSource;
        }

        $submission = new Submission([
            'form_id' => $form->id,
            'uuid' => $submissionUuid,
            'status' => 'completed',
            'is_encrypted' => (bool) $form->is_encrypted,
            'current_step' => 1,
            'data' => $data,
            'meta' => $meta,
        ]);
        $submission->save();

        // 6. Dispatch Notification Email (Amazon SES Relay / Custom In-App SMTP)
        if ($hasEmail) {
            if ($form->recipients()->count() === 0) {
                $form->syncNotificationRecipients();
            }

            $recipients = $form->recipients()
                ->whereNotNull('verified_at')
                ->pluck('email')
                ->all();

            if (! empty($recipients)) {
                try {
                    Mail::to($recipients)->send(new SubmissionReceived($form, $submission));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        // 7. Dispatch Outbound Webhook (with HMAC SHA-256 signature and exponential retries)
        if (! empty($form->webhook_url)) {
            try {
                DispatchFormWebhook::dispatch($form, $submission, $submission->getDataWithSignedUrls());
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // 8. Dispatch Chat Notification Channels (Slack, Discord, Telegram)
        foreach ($form->notificationChannels()->where('is_active', true)->get() as $channel) {
            try {
                SendChatNotification::dispatch($channel, $submission);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // 9. Deduct Metred Pay-As-You-Go Credits (1 credit for webhook/DB, 5 credits for email relay)
        if (config('flowform.metering.enabled', false)) {
            $form->user?->deductCredits($hasEmail);
        }

        return $this->respondSuccess($request, $form, $submission);
    }

    /** @return array{name: string, size: int, mime: string, path: string} */
    protected function storeUpload(UploadedFile $file, string $submissionUuid): array
    {
        $path = $file->storeAs(
            "submissions/{$submissionUuid}",
            Str::random(40).'.'.$file->guessExtension(),
            ['disk' => config('flowform.uploads.disk')]
        );

        return [
            'name' => str_replace(['/', '\\'], '_', $file->getClientOriginalName()),
            'size' => (int) $file->getSize(),
            'mime' => (string) $file->getMimeType(),
            'path' => (string) $path,
        ];
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

            // Wildcard matching e.g. *.example.com
            if (str_starts_with($pattern, '*.')) {
                $rootDomain = substr($pattern, 2);
                if ($host === $rootDomain || str_ends_with($host, '.'.$rootDomain)) {
                    return true;
                }
            }
        }

        return false;
    }
}
