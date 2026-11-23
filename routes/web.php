<?php

use App\Http\Controllers\Api\PublicEndpointController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\NotificationVerificationController;
use App\Http\Controllers\SubmissionFileController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('notifications/verify/{recipient}', [NotificationVerificationController::class, 'show'])
    ->name('notifications.verify')
    ->middleware('signed');

Route::post('notifications/verify/{recipient}', [NotificationVerificationController::class, 'verify'])
    ->name('notifications.verify.confirm')
    ->middleware('signed');

Route::get('admin/mail-settings-page', function () {
    return redirect('/admin/mail-settings');
});

Route::get('admin/submissions/{submission}/files/{index}', [SubmissionFileController::class, 'download'])
    ->whereNumber(['submission', 'index'])
    ->name('admin.submissions.files.download');

Route::post('f/{slug}', [PublicEndpointController::class, 'submit'])
    ->middleware('throttle:endpoint-submissions')
    ->name('forms.endpoint.submit');

Route::middleware(['web', 'guest'])->group(function () {
    Route::get('auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->name('social.redirect');

    Route::get('auth/{provider}/callback', [SocialLoginController::class, 'callback'])
        ->name('social.callback');
});

if (config('flowform.metering.enabled', false)) {
    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('billing/upgrade', [BillingController::class, 'upgrade'])->name('billing.upgrade');
        Route::get('billing/success', [BillingController::class, 'success'])->name('billing.success');
        Route::get('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    });

    Route::post('api/v1/zoneless/webhook', [BillingController::class, 'webhook'])
        ->withoutMiddleware([PreventRequestForgery::class])
        ->name('zoneless.webhook');
}
