<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Form;
use App\Models\MailSetting;
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

        $mailSetting = MailSetting::create([
            'driver' => 'smtp',
            'host' => 'smtp.mailtrap.io',
            'port' => 2525,
            'is_active' => true,
        ]);

        $form = Form::create([
            'user_id' => $user->id,
            'name' => 'Intake Form',
            'slug' => 'intake',
            'type' => 'endpoint',
            'is_active' => true,
        ]);

        // Submission with user_id
        Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $form->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'data' => ['email' => 'client@acme.com', 'notes' => 'Confidential patient inquiry'],
        ]);

        // Submission without user_id (e.g. from public endpoint) belonging to user's form
        Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $form->id,
            'user_id' => null,
            'status' => 'completed',
            'data' => ['email' => 'public@acme.com', 'notes' => 'Public lead'],
        ]);

        // Submission made to another tenant's form
        $otherTenant = User::factory()->create();
        $otherForm = Form::create([
            'user_id' => $otherTenant->id,
            'name' => 'Other Tenant Form',
            'slug' => 'other-tenant-form',
            'type' => 'endpoint',
            'is_active' => true,
        ]);
        Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $otherForm->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'data' => ['email' => $user->email, 'feedback' => 'External feedback'],
        ]);

        $response = $this->getJson('/api/v1/compliance/gdpr/export');

        $response->assertStatus(200)
            ->assertHeader('Content-Disposition')
            ->assertJsonPath('tenant.email', $user->email)
            ->assertJsonCount(1, 'forms')
            ->assertJsonMissingPath('submissions')
            ->assertJsonCount(2, 'submissions_received')
            ->assertJsonCount(1, 'submissions_made')
            ->assertJsonMissingPath('mail_settings');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'gdpr.export',
        ]);

        // Instance mail setting must remain intact
        $this->assertDatabaseHas('mail_settings', [
            'id' => $mailSetting->id,
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
            'user_id' => null,
            'status' => 'completed',
            'data' => ['msg' => 'sub2'],
        ]);

        $response = $this->postJson('/api/v1/compliance/gdpr/erasure', [
            'purge_all_submissions' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('erased_submissions_count', 2);

        $this->assertEquals(0, Submission::where('user_id', $user->id)->orWhere('form_id', $form->id)->count());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'gdpr.erasure.all_submissions',
        ]);
    }

    public function test_tenant_can_delete_account_and_erase_personal_data_leaving_instance_mail_settings_intact(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        Sanctum::actingAs($user);

        $mailSetting = MailSetting::create([
            'driver' => 'smtp',
            'host' => 'smtp.mailtrap.io',
            'is_active' => true,
        ]);

        $form = Form::create([
            'user_id' => $user->id,
            'name' => 'Account Form',
            'slug' => 'account-form',
            'type' => 'endpoint',
            'is_active' => true,
        ]);

        $submission = Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $form->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'data' => ['email' => 'client@acme.com'],
        ]);

        $response = $this->postJson('/api/v1/compliance/gdpr/erasure', [
            'delete_account' => true,
            'confirm' => 'DELETE',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('mode', 'account_erased');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('forms', ['id' => $form->id]);
        $this->assertDatabaseMissing('submissions', ['id' => $submission->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);

        // Instance mail setting must be preserved
        $this->assertDatabaseHas('mail_settings', ['id' => $mailSetting->id]);
    }

    public function test_account_erasure_requires_confirm_parameter(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/compliance/gdpr/erasure', [
            'delete_account' => true,
        ]);

        $response->assertStatus(422);

        $responseInvalid = $this->postJson('/api/v1/compliance/gdpr/erasure', [
            'delete_account' => true,
            'confirm' => 'WRONG',
        ]);

        $responseInvalid->assertStatus(422);
    }

    public function test_erasure_of_user_a_does_not_touch_user_b_data(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $mailSetting = MailSetting::create([
            'driver' => 'smtp',
            'host' => 'smtp.mailtrap.io',
            'is_active' => true,
        ]);

        $formA = Form::create([
            'user_id' => $userA->id,
            'name' => 'Form A',
            'slug' => 'form-a',
            'type' => 'endpoint',
            'is_active' => true,
        ]);

        $subA = Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $formA->id,
            'user_id' => $userA->id,
            'status' => 'completed',
            'data' => ['email' => 'shared@example.com'],
        ]);

        $formB = Form::create([
            'user_id' => $userB->id,
            'name' => 'Form B',
            'slug' => 'form-b',
            'type' => 'endpoint',
            'is_active' => true,
        ]);

        $subB = Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $formB->id,
            'user_id' => $userB->id,
            'status' => 'completed',
            'data' => ['email' => 'shared@example.com'],
        ]);

        // Act as User A and delete account
        Sanctum::actingAs($userA);

        $response = $this->postJson('/api/v1/compliance/gdpr/erasure', [
            'delete_account' => true,
            'confirm' => 'DELETE',
        ]);

        $response->assertStatus(200);

        // User A's data should be erased
        $this->assertDatabaseMissing('users', ['id' => $userA->id]);
        $this->assertDatabaseMissing('forms', ['id' => $formA->id]);
        $this->assertDatabaseMissing('submissions', ['id' => $subA->id]);

        // User B's data must remain intact
        $this->assertDatabaseHas('users', ['id' => $userB->id]);
        $this->assertDatabaseHas('forms', ['id' => $formB->id]);
        $this->assertDatabaseHas('submissions', ['id' => $subB->id]);

        // Instance mail setting must remain intact
        $this->assertDatabaseHas('mail_settings', ['id' => $mailSetting->id]);
    }

    public function test_account_erasure_unlinks_submissions_made_to_other_tenant_forms_leaving_them_intact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $formA = Form::create([
            'user_id' => $userA->id,
            'name' => 'Form A',
            'slug' => 'form-a',
            'type' => 'endpoint',
            'is_active' => true,
        ]);

        $formB = Form::create([
            'user_id' => $userB->id,
            'name' => 'Form B',
            'slug' => 'form-b',
            'type' => 'endpoint',
            'is_active' => true,
        ]);

        $subOwn = Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $formA->id,
            'user_id' => $userA->id,
            'status' => 'completed',
            'data' => ['message' => 'Own submission'],
        ]);

        // User A submitted to User B's form while authenticated
        $subOnB = Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $formB->id,
            'user_id' => $userA->id,
            'status' => 'completed',
            'data' => ['message' => 'Feedback for B'],
        ]);

        Sanctum::actingAs($userA);

        $response = $this->postJson('/api/v1/compliance/gdpr/erasure', [
            'delete_account' => true,
            'confirm' => 'DELETE',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('mode', 'account_erased');

        // User A and their own form/submission are gone
        $this->assertDatabaseMissing('users', ['id' => $userA->id]);
        $this->assertDatabaseMissing('forms', ['id' => $formA->id]);
        $this->assertDatabaseMissing('submissions', ['id' => $subOwn->id]);

        // Submission on User B's form must STILL EXIST with user_id = null
        $this->assertDatabaseHas('submissions', [
            'id' => $subOnB->id,
            'form_id' => $formB->id,
            'user_id' => null,
        ]);

        // User B can still access their submission
        Sanctum::actingAs($userB);
        $getResponse = $this->getJson("/api/v1/submissions/{$subOnB->uuid}");
        $getResponse->assertStatus(200);
    }

    public function test_purge_all_submissions_does_not_touch_submissions_on_other_tenant_forms(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $formA = Form::create([
            'user_id' => $userA->id,
            'name' => 'Form A',
            'slug' => 'form-a',
            'type' => 'endpoint',
            'is_active' => true,
        ]);

        $formB = Form::create([
            'user_id' => $userB->id,
            'name' => 'Form B',
            'slug' => 'form-b',
            'type' => 'endpoint',
            'is_active' => true,
        ]);

        $subOwn = Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $formA->id,
            'user_id' => $userA->id,
            'status' => 'completed',
            'data' => ['message' => 'Own submission'],
        ]);

        // User A submitted to User B's form
        $subOnB = Submission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $formB->id,
            'user_id' => $userA->id,
            'status' => 'completed',
            'data' => ['message' => 'Submission to B'],
        ]);

        Sanctum::actingAs($userA);

        $response = $this->postJson('/api/v1/compliance/gdpr/erasure', [
            'purge_all_submissions' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('erased_submissions_count', 1);

        // Own form's submission is deleted
        $this->assertDatabaseMissing('submissions', ['id' => $subOwn->id]);

        // Submission on User B's form remains intact
        $this->assertDatabaseHas('submissions', [
            'id' => $subOnB->id,
            'form_id' => $formB->id,
            'user_id' => $userA->id,
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
