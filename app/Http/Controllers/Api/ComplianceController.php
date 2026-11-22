<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\MailSetting;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplianceController extends Controller
{
    /**
     * Return public Data Processing Addendum (DPA) and compliance commitments.
     */
    public function dpa(): JsonResponse
    {
        return response()->json([
            'status' => 'active',
            'version' => '2026-v1.0',
            'frameworks' => [
                'GDPR' => [
                    'compliance_status' => 'Compliant (Articles 15, 17, 20, 28, 32)',
                    'data_residency' => 'Self-Hosted (Local / Sovereign) or FlowForm Managed Cloud (EU/US)',
                    'rights_supported' => [
                        'Right of Access (Article 15)' => '/api/v1/compliance/gdpr/export',
                        'Right to Erasure / Right to be Forgotten (Article 17)' => '/api/v1/compliance/gdpr/erasure',
                        'Right to Data Portability (Article 20)' => '/api/v1/compliance/gdpr/export',
                    ],
                ],
                'HIPAA' => [
                    'compliance_status' => 'Safeguards Implemented (45 CFR § 164.312)',
                    'technical_safeguards' => [
                        'Encryption at Rest' => 'AES-256-GCM / Laravel Crypt',
                        'Encryption in Transit' => 'TLS 1.3 / HTTPS Strict Transport Security',
                        'Audit Controls (§ 164.312(b))' => 'Immutable audit logging for encrypted submission access',
                        'Automatic Logoff (§ 164.312(a)(2)(iii))' => 'Session lifetime inactivity controls',
                    ],
                    'baa_available' => true,
                    'contact' => 'compliance@flowformhq.com',
                ],
                'SOC2' => [
                    'status' => 'Security, Availability, and Confidentiality controls implemented',
                ],
            ],
            'subprocessors' => [
                ['name' => 'Amazon Web Services (AWS)', 'role' => 'Cloud Infrastructure & SES Mail Delivery', 'country' => 'Global'],
                ['name' => 'Cloudflare, Inc.', 'role' => 'DDoS Mitigation & Edge Routing', 'country' => 'Global'],
                ['name' => 'Resend / Postmark', 'role' => 'Transactional Email (Optional customer configured)', 'country' => 'United States'],
            ],
        ]);
    }

    /**
     * GDPR Article 20: Automated Data Portability / JSON Export.
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        $forms = Form::where('user_id', $user->id)
            ->with(['steps.fields', 'steps.conditions'])
            ->get();

        $submissions = Submission::where('user_id', $user->id)
            ->with('values')
            ->get()
            ->map(function (Submission $submission) {
                return [
                    'uuid' => $submission->uuid,
                    'form_id' => $submission->form_id,
                    'status' => $submission->status,
                    'is_encrypted' => (bool) $submission->is_encrypted,
                    'payload' => $submission->data, // automatically decrypted via Submission mutator if encrypted
                    'created_at' => $submission->created_at?->toIso8601String(),
                ];
            });

        $mailSettings = MailSetting::where('user_id', $user->id)
            ->get()
            ->map(fn (MailSetting $ms) => [
                'provider' => $ms->provider,
                'from_address' => $ms->from_address,
                'from_name' => $ms->from_name,
                'is_active' => $ms->is_active,
            ]);

        AuditLog::record(
            action: 'gdpr.export',
            auditable: $user,
            details: [
                'forms_exported' => $forms->count(),
                'submissions_exported' => $submissions->count(),
            ],
            user: $user,
            request: $request
        );

        $payload = [
            'tenant' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'plan' => $user->plan ?? 'free',
                'exported_at' => now()->toIso8601String(),
            ],
            'forms' => $forms,
            'submissions' => $submissions,
            'mail_settings' => $mailSettings,
        ];

        return response()->json($payload, 200, [
            'Content-Disposition' => sprintf('attachment; filename="flowform-gdpr-export-tenant-%s.json"', $user->id),
        ]);
    }

    /**
     * GDPR Article 17: Right to be Forgotten / Data Erasure.
     */
    public function erasure(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'nullable|email',
            'purge_all_submissions' => 'nullable|boolean',
            'delete_account' => 'nullable|boolean',
            'confirm' => 'required_if:delete_account,true|string',
        ]);

        $user = $request->user();

        // 1. Data Subject Specific Erasure by Email
        if ($request->filled('email')) {
            $email = strtolower(trim((string) $request->input('email')));

            $userFormIds = Form::where('user_id', $user->id)->pluck('id');

            // Find matching submissions under tenant forms
            $matchingSubmissions = Submission::where(function ($query) use ($user, $userFormIds) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('form_id', $userFormIds);
            })->get()->filter(function (Submission $sub) use ($email) {
                $data = $sub->data ?? [];
                if (is_array($data)) {
                    foreach ($data as $val) {
                        if (is_string($val) && strtolower(trim($val)) === $email) {
                            return true;
                        }
                    }
                }
                return false;
            });

            $count = $matchingSubmissions->count();
            foreach ($matchingSubmissions as $sub) {
                $sub->values()->delete();
                $sub->delete();
            }

            AuditLog::record(
                action: 'gdpr.erasure.subject',
                auditable: $user,
                details: [
                    'subject_email' => $email,
                    'erased_submissions_count' => $count,
                ],
                user: $user,
                request: $request
            );

            return response()->json([
                'status' => 'success',
                'mode' => 'data_subject_erasure',
                'email' => $email,
                'erased_submissions_count' => $count,
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        // 2. Purge All Submissions
        if ($request->boolean('purge_all_submissions')) {
            $userFormIds = Form::where('user_id', $user->id)->pluck('id');

            $submissionsQuery = Submission::where('user_id', $user->id)
                ->orWhereIn('form_id', $userFormIds);

            $count = $submissionsQuery->count();

            DB::transaction(function () use ($user, $userFormIds) {
                $subIds = Submission::where('user_id', $user->id)
                    ->orWhereIn('form_id', $userFormIds)
                    ->pluck('id');

                DB::table('submission_values')->whereIn('submission_id', $subIds)->delete();
                Submission::whereIn('id', $subIds)->delete();
            });

            AuditLog::record(
                action: 'gdpr.erasure.all_submissions',
                auditable: $user,
                details: [
                    'erased_submissions_count' => $count,
                ],
                user: $user,
                request: $request
            );

            return response()->json([
                'status' => 'success',
                'mode' => 'purge_all_submissions',
                'erased_submissions_count' => $count,
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        // 3. Full Account Deletion
        if ($request->boolean('delete_account')) {
            if ($request->input('confirm') !== 'DELETE') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please provide confirm="DELETE" to proceed with irreversible account deletion.',
                ], 422);
            }

            DB::transaction(function () use ($user) {
                $userFormIds = Form::where('user_id', $user->id)->pluck('id');
                $subIds = Submission::where('user_id', $user->id)
                    ->orWhereIn('form_id', $userFormIds)
                    ->pluck('id');

                DB::table('submission_values')->whereIn('submission_id', $subIds)->delete();
                Submission::whereIn('id', $subIds)->delete();
                MailSetting::where('user_id', $user->id)->delete();
                Form::where('user_id', $user->id)->delete();
                $user->tokens()->delete();
                $user->delete();
            });

            return response()->json([
                'status' => 'success',
                'mode' => 'account_erased',
                'message' => 'User account and all associated forms, submissions, and credentials have been permanently deleted.',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Specify either email, purge_all_submissions=true, or delete_account=true.',
        ], 400);
    }
}
