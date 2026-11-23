<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchFormWebhook;
use App\Mail\SubmissionReceived;
use App\Models\Form;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

        // 3b. Server-Side Captcha Verification (Cloudflare Turnstile, hCaptcha)
        if ($form->hasCaptcha()) {
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

        // 4b. Validate and Process File Uploads (if any)
        $allFiles = [];
        $flattenFiles = function ($items) use (&$flattenFiles, &$allFiles): void {
            foreach ($items as $item) {
                if ($item instanceof UploadedFile) {
                    $allFiles[] = $item;
                } elseif (is_array($item)) {
                    $flattenFiles($item);
                }
            }
        };
        $flattenFiles($request->allFiles());

        $uploadedFilesData = [];
        if (! empty($allFiles)) {
            $maxFiles = (int) config('flowform.uploads.max_files', 5);
            if (count($allFiles) > $maxFiles) {
                return response()->json([
                    'success' => false,
                    'error' => "The maximum number of allowed files is {$maxFiles}.",
                    'errors' => [
                        'files' => ["The maximum number of allowed files is {$maxFiles}."],
                    ],
                ], 422)->header('Access-Control-Allow-Origin', '*');
            }

            $maxKb = (int) config('flowform.uploads.max_kb', 10240);
            $rawMimes = config('flowform.uploads.allowed_mimes', ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'zip']);
            $allowedMimes = is_array($rawMimes) ? $rawMimes : explode(',', (string) $rawMimes);
            $allowedMimes = array_values(array_filter(array_map(fn ($m) => strtolower(trim(ltrim($m, '.'))), $allowedMimes)));

            $mimeExtensionMap = [
                'application/pdf' => ['pdf'],
                'image/png' => ['png'],
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/gif' => ['gif'],
                'image/webp' => ['webp'],
                'text/plain' => ['txt', 'csv'],
                'text/csv' => ['csv'],
                'application/csv' => ['csv'],
                'application/msword' => ['doc'],
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
                'application/vnd.ms-excel' => ['xls'],
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
                'application/zip' => ['zip'],
                'application/x-zip' => ['zip'],
                'application/x-zip-compressed' => ['zip'],
            ];

            $errors = [];
            foreach ($request->allFiles() as $field => $fileOrFiles) {
                $fieldFiles = is_array($fileOrFiles) ? $fileOrFiles : [$fileOrFiles];
                foreach ($fieldFiles as $file) {
                    if (! ($file instanceof UploadedFile)) {
                        continue;
                    }

                    if ($file->getSize() > ($maxKb * 1024)) {
                        $errors[$field][] = "The file {$file->getClientOriginalName()} exceeds the maximum allowed size of {$maxKb} KB.";

                        continue;
                    }

                    $ext = strtolower(ltrim((string) $file->getClientOriginalExtension(), '.'));
                    $clientMime = strtolower((string) ($file->getClientMimeType() ?: ''));
                    $guessedMime = strtolower((string) ($file->getMimeType() ?: ''));

                    $isAllowed = in_array($ext, $allowedMimes, true)
                        || in_array($clientMime, $allowedMimes, true)
                        || in_array($guessedMime, $allowedMimes, true);

                    if (! $isAllowed) {
                        foreach ([$clientMime, $guessedMime] as $m) {
                            if (isset($mimeExtensionMap[$m]) && ! empty(array_intersect($mimeExtensionMap[$m], $allowedMimes))) {
                                $isAllowed = true;
                                break;
                            }
                        }
                    }

                    if (! $isAllowed) {
                        $errors[$field][] = "The file {$file->getClientOriginalName()} has a disallowed type ({$ext}).";
                    }
                }
            }

            if (! empty($errors)) {
                return response()->json([
                    'success' => false,
                    'error' => 'File upload validation failed.',
                    'errors' => $errors,
                ], 422)->header('Access-Control-Allow-Origin', '*');
            }
        }

        $submissionUuid = Str::uuid()->toString();

        if (! empty($allFiles)) {
            $diskName = config('flowform.uploads.disk', 'local');
            $disk = Storage::disk($diskName);

            foreach ($request->allFiles() as $field => $fileOrFiles) {
                if (is_array($fileOrFiles)) {
                    $list = [];
                    foreach ($fileOrFiles as $file) {
                        if ($file instanceof UploadedFile) {
                            $ext = $file->guessExtension() ?: $file->getClientOriginalExtension();
                            $filename = Str::random(40).($ext ? ".{$ext}" : '');
                            $path = "submissions/{$submissionUuid}/{$filename}";
                            $disk->putFileAs("submissions/{$submissionUuid}", $file, $filename);
                            $list[] = [
                                'name' => $file->getClientOriginalName(),
                                'size' => $file->getSize(),
                                'mime' => $file->getClientMimeType() ?: $file->getMimeType(),
                                'path' => $path,
                            ];
                        }
                    }
                    $uploadedFilesData[$field] = $list;
                } elseif ($fileOrFiles instanceof UploadedFile) {
                    $ext = $fileOrFiles->guessExtension() ?: $fileOrFiles->getClientOriginalExtension();
                    $filename = Str::random(40).($ext ? ".{$ext}" : '');
                    $path = "submissions/{$submissionUuid}/{$filename}";
                    $disk->putFileAs("submissions/{$submissionUuid}", $fileOrFiles, $filename);
                    $uploadedFilesData[$field] = [
                        'name' => $fileOrFiles->getClientOriginalName(),
                        'size' => $fileOrFiles->getSize(),
                        'mime' => $fileOrFiles->getClientMimeType() ?: $fileOrFiles->getMimeType(),
                        'path' => $path,
                    ];
                }
            }
        }

        // 5. Clean and Store Payload (with Field-Level AES-256 Encryption if enabled)
        $reservedKeys = [
            '_gotcha',
            '_honeypot',
            '_next',
            '_redirect',
            '_token',
            '_method',
            '_replyto',
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

        $submission = new Submission([
            'form_id' => $form->id,
            'uuid' => $submissionUuid,
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

        // 8. Deduct Metred Pay-As-You-Go Credits (1 credit for webhook/DB, 5 credits for email relay)
        if (config('flowform.metering.enabled', false)) {
            $form->user?->deductCredits($hasEmail);
        }

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
