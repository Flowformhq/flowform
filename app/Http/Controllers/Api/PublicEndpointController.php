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
