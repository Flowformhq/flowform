<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dpa_endpoint_returns_framework_commitments(): void
    {
        $response = $this->getJson('/api/v1/compliance/dpa');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'version',
                'frameworks' => [
                    'GDPR' => ['compliance_status', 'rights_supported'],
                    'HIPAA' => ['compliance_status', 'technical_safeguards', 'baa_available'],
                    'SOC2',
                ],
                'subprocessors',
            ]);
    }

    public function test_unauthenticated_requests_cannot_access_gdpr_export_or_erasure(): void
    {
        $this->getJson('/api/v1/compliance/gdpr/export')->assertStatus(401);
        $this->postJson('/api/v1/compliance/gdpr/erasure', ['email' => 'test@example.com'])->assertStatus(401);
    }

    public function test_tenant_can_export_all_data_for_portability(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $form = Form::create([
            'user_id' => $user->id,
            'name' => 'Intake Form',
            'slug' => 'intake',
            'type' => 'endpoint',
            'is_active' => true,
        ]);

        $submission = Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $form->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'data' => ['email' => 'client@acme.com', 'notes' => 'Confidential patient inquiry'],
        ]);

        $response = $this->getJson('/api/v1/compliance/gdpr/export');

        $response->assertStatus(200)
            ->assertHeader('Content-Disposition')
            ->assertJsonPath('tenant.email', $user->email)
            ->assertJsonCount(1, 'forms')
            ->assertJsonCount(1, 'submissions');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'gdpr.export',
        ]);
    }

    public function test_tenant_can_erase_data_subject_submissions_by_email(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $form = Form::create([
            'user_id' => $user->id,
            'name' => 'Support Form',
            'slug' => 'support',
            'type' => 'endpoint',
            'is_active' => true,
        ]);

        $targetSub = Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $form->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'data' => ['email' => 'erasure-request@example.com', 'message' => 'Please forget me'],
        ]);

        $otherSub = Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $form->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'data' => ['email' => 'keep-me@example.com', 'message' => 'Stay'],
        ]);

        $response = $this->postJson('/api/v1/compliance/gdpr/erasure', [
            'email' => 'erasure-request@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('erased_submissions_count', 1);

        $this->assertDatabaseMissing('submissions', ['id' => $targetSub->id]);
        $this->assertDatabaseHas('submissions', ['id' => $otherSub->id]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'gdpr.erasure.subject',
        ]);
    }

    public function test_tenant_can_purge_all_submissions(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $form = Form::create([
            'user_id' => $user->id,
            'name' => 'Inquiry Form',
            'slug' => 'inquiry',
            'type' => 'endpoint',
            'is_active' => true,
        ]);

        Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $form->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'data' => ['msg' => 'sub1'],
        ]);
        Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $form->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'data' => ['msg' => 'sub2'],
        ]);

        $response = $this->postJson('/api/v1/compliance/gdpr/erasure', [
            'purge_all_submissions' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('erased_submissions_count', 2);

        $this->assertEquals(0, Submission::where('user_id', $user->id)->count());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'gdpr.erasure.all_submissions',
        ]);
    }

    public function test_viewing_encrypted_submission_records_hipaa_audit_trail(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $form = Form::create([
            'user_id' => $user->id,
            'name' => 'HIPAA Intake',
            'slug' => 'hipaa-intake',
            'type' => 'endpoint',
            'is_encrypted' => true,
            'is_active' => true,
        ]);

        $submission = Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $form->id,
            'user_id' => $user->id,
            'is_encrypted' => true,
            'status' => 'completed',
            'data' => ['diagnosis' => 'PHI Sensitive Clinical Data'],
        ]);

        $response = $this->getJson("/api/v1/submissions/{$submission->uuid}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'submission.decrypted',
            'auditable_type' => Submission::class,
            'auditable_id' => $submission->id,
        ]);
    }
}
