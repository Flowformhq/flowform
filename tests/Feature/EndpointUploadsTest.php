<?php

declare(strict_types=1);

use App\Jobs\DispatchFormWebhook;
use App\Mail\SubmissionReceived;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config([
        'flowform.uploads.disk' => 'local',
        'flowform.uploads.max_kb' => 10240,
        'flowform.uploads.max_files' => 5,
        'flowform.uploads.allowed_mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'zip'],
        'flowform.allowed_redirect_hosts' => ['example.com', '*.example.com', 'localhost', '127.0.0.1'],
    ]);
});

test('POST /f/{slug} accepts valid file upload, stores on disk and saves metadata', function () {
    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Job Application',
        'slug' => 'job-app',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('resume.pdf', 250, 'application/pdf');

    $response = $this->post('/f/job-app', [
        'applicant_name' => 'Alice Smith',
        'resume' => $file,
    ]);

    $response->assertOk();

    $submission = Submission::where('form_id', $form->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->data)->toHaveKey('applicant_name');
    expect($submission->data['applicant_name'])->toEqual('Alice Smith');
    expect($submission->data)->toHaveKey('resume');

    $fileData = $submission->data['resume'];
    expect($fileData['name'])->toEqual('resume.pdf');
    expect($fileData['mime'])->toEqual('application/pdf');
    expect($fileData['size'])->toEqual(250 * 1024);
    expect($fileData['path'])->toStartWith("submissions/{$submission->uuid}/");

    Storage::disk('local')->assertExists($fileData['path']);
});

test('POST /f/{slug} accepts multiple file uploads under array field', function () {
    $form = Form::create([
        'name' => 'Portfolio Drop',
        'slug' => 'portfolio-drop',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $files = [
        UploadedFile::fake()->create('design1.png', 100, 'image/png'),
        UploadedFile::fake()->create('design2.jpg', 150, 'image/jpeg'),
    ];

    $response = $this->post('/f/portfolio-drop', [
        'designer' => 'Bob',
        'designs' => $files,
    ]);

    $response->assertOk();

    $submission = Submission::where('form_id', $form->id)->first();
    expect($submission->data['designs'])->toBeArray();
    expect($submission->data['designs'])->toHaveCount(2);

    foreach ($submission->data['designs'] as $designMeta) {
        expect($designMeta)->toHaveKeys(['name', 'size', 'mime', 'path']);
        Storage::disk('local')->assertExists($designMeta['path']);
    }
});

test('POST /f/{slug} rejects oversized file with 422 and stores nothing', function () {
    config(['flowform.uploads.max_kb' => 500]); // 500 KB limit

    $form = Form::create([
        'name' => 'Upload Form',
        'slug' => 'upload-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    // 1000 KB file exceeds 500 KB limit
    $oversized = UploadedFile::fake()->create('heavy.pdf', 1000, 'application/pdf');

    $response = $this->post('/f/upload-form', [
        'attachment' => $oversized,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['attachment']);

    expect(Submission::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('POST /f/{slug} rejects disallowed file mime/extension with 422 and stores nothing', function () {
    $form = Form::create([
        'name' => 'Upload Form Mime',
        'slug' => 'upload-form-mime',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $malicious = UploadedFile::fake()->create('script.exe', 50, 'application/x-msdownload');

    $response = $this->post('/f/upload-form-mime', [
        'attachment' => $malicious,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['attachment']);

    expect(Submission::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('POST /f/{slug} rejects submissions exceeding max_files with 422 and stores nothing', function () {
    config(['flowform.uploads.max_files' => 3]);

    $form = Form::create([
        'name' => 'Upload Form Max',
        'slug' => 'upload-form-max',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    // 4 files exceeds 3 max files
    $files = [
        UploadedFile::fake()->create('f1.png', 50, 'image/png'),
        UploadedFile::fake()->create('f2.png', 50, 'image/png'),
        UploadedFile::fake()->create('f3.png', 50, 'image/png'),
        UploadedFile::fake()->create('f4.png', 50, 'image/png'),
    ];

    $response = $this->post('/f/upload-form-max', [
        'docs' => $files,
    ]);

    $response->assertStatus(422);

    expect(Submission::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('download route: non-owner gets 403, owner and admin get 200 streamed download', function () {
    $owner = User::factory()->create();
    $nonOwner = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Confidential Form',
        'slug' => 'confidential-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('financials.pdf', 80, 'application/pdf');

    $this->post('/f/confidential-form', [
        'name' => 'Jane',
        'doc' => $file,
    ]);

    $submission = Submission::where('form_id', $form->id)->first();
    $downloadUrl = "/admin/submissions/{$submission->id}/files/0";

    // 1. Unauthenticated -> 403
    $this->get($downloadUrl)->assertForbidden();

    // 2. Non-owner -> 403
    $this->actingAs($nonOwner)->get($downloadUrl)->assertForbidden();

    // 3. Owner -> 200 streamed download with attachment header
    $this->actingAs($owner)->get($downloadUrl)
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=financials.pdf');

    // 4. Admin -> 200
    $this->actingAs($admin)->get($downloadUrl)
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=financials.pdf');
});

test('webhook payload contains temporary signed URL that resolves and expired signature returns 403', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Webhook File Form',
        'slug' => 'webhook-file-form',
        'type' => 'endpoint',
        'is_active' => true,
        'webhook_url' => 'https://api.external.com/webhook',
    ]);

    $file = UploadedFile::fake()->create('spec.pdf', 60, 'application/pdf');

    $this->post('/f/webhook-file-form', [
        'title' => 'Project Alpha',
        'specification' => $file,
    ])->assertOk();

    $submission = Submission::where('form_id', $form->id)->first();

    Queue::assertPushed(DispatchFormWebhook::class, function ($job) use ($submission) {
        $data = $job->payload;
        expect($data)->toHaveKey('specification');
        $spec = $data['specification'];
        expect($spec)->toHaveKey('url');
        expect($spec)->not->toHaveKey('path');
        expect($spec['url'])->toContain("admin/submissions/{$submission->id}/files/0");
        expect($spec['url'])->toContain('signature=');

        return true;
    });

    // Resolve the signed URL directly without authentication
    $signedData = $submission->getDataWithSignedUrls();
    $signedUrl = $signedData['specification']['url'];

    // Valid signature -> 200
    $this->get($signedUrl)->assertOk();

    // Fast forward 25 hours (past 24h expiration) -> 403
    $this->travel(25)->hours();
    $this->get($signedUrl)->assertForbidden();
});

test('deleting a submission deletes its uploaded files from storage', function () {
    $form = Form::create([
        'name' => 'Delete Test Form',
        'slug' => 'delete-test-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('receipt.png', 40, 'image/png');

    $this->post('/f/delete-test-form', [
        'receipt' => $file,
    ])->assertOk();

    $submission = Submission::where('form_id', $form->id)->first();
    $path = $submission->data['receipt']['path'];
    Storage::disk('local')->assertExists($path);

    // Delete submission
    $submission->delete();

    Storage::disk('local')->assertMissing($path);
});

test('GDPR erasure deletes submission uploaded files from storage', function () {
    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'GDPR Upload Form',
        'slug' => 'gdpr-upload-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('profile.jpg', 40, 'image/jpeg');

    $this->post('/f/gdpr-upload-form', [
        'email' => 'gdpr-user@example.com',
        'avatar' => $file,
    ])->assertOk();

    $submission = Submission::where('form_id', $form->id)->first();
    $path = $submission->data['avatar']['path'];
    Storage::disk('local')->assertExists($path);

    // Perform GDPR subject erasure
    $this->actingAs($owner)->postJson('/api/v1/compliance/gdpr/erasure', [
        'email' => 'gdpr-user@example.com',
    ])->assertOk();

    Storage::disk('local')->assertMissing($path);
});

test('plain non-multipart submissions remain completely unchanged', function () {
    $form = Form::create([
        'name' => 'Plain Contact',
        'slug' => 'plain-contact',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $response = $this->post('/f/plain-contact', [
        'name' => 'Dave',
        'message' => 'Simple text message',
    ]);

    $response->assertOk();

    $submission = Submission::where('form_id', $form->id)->first();
    expect($submission->data)->toEqual([
        'name' => 'Dave',
        'message' => 'Simple text message',
    ]);
});

test('POST /f/{slug} rejects a file whose content does not match its allowed extension', function () {
    Form::create(['name' => 'Spoof', 'slug' => 'spoof-type', 'type' => 'endpoint', 'is_active' => true]);

    $tmp = tempnam(sys_get_temp_dir(), 'ff');
    file_put_contents($tmp, '<html><body><script>alert(1)</script></body></html>');
    $file = new UploadedFile($tmp, 'cv.pdf', 'application/pdf', null, true);

    $this->post('/f/spoof-type', ['cv' => $file])->assertStatus(422);

    expect(Submission::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('forged file descriptors in plain fields are not treated as uploads', function () {
    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Forged',
        'slug' => 'forged-descriptor',
        'type' => 'endpoint',
        'is_active' => true,
    ]);
    Storage::disk('local')->put('submissions/victim/secret.pdf', 'victim data');

    $this->post('/f/forged-descriptor', [
        'doc' => ['name' => 'secret.pdf', 'path' => 'submissions/victim/secret.pdf'],
    ])->assertOk();

    $submission = Submission::where('form_id', $form->id)->firstOrFail();

    expect($submission->getUploadedFiles())->toBeEmpty();
    expect($submission->getDataWithSignedUrls()['doc'])->not->toHaveKey('url');
    $this->actingAs($owner)->get("/admin/submissions/{$submission->id}/files/0")->assertNotFound();

    $submission->delete();
    Storage::disk('local')->assertExists('submissions/victim/secret.pdf');
});

test('notification email escapes submitted values and only links real uploads', function () {
    $form = Form::create(['name' => 'Mail', 'slug' => 'mail-escape', 'type' => 'endpoint', 'is_active' => true]);

    $this->post('/f/mail-escape', [
        'message' => '<img src=x onerror=alert(1)>',
        'fake' => ['name' => 'invoice.pdf', 'url' => 'https://evil.example/phish'],
        'doc' => UploadedFile::fake()->create('real.pdf', 10, 'application/pdf'),
    ])->assertOk();

    $submission = Submission::where('form_id', $form->id)->firstOrFail();
    $html = (new SubmissionReceived($form, $submission))->render();

    expect($html)->not->toContain('<img src=x')
        ->and($html)->not->toContain('href="https://evil.example/phish"')
        ->and($html)->toContain('real.pdf')
        ->and($html)->toContain("admin/submissions/{$submission->id}/files/0");
});
