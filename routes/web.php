<?php

use App\Http\Controllers\Api\PublicEndpointController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\BillingController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('admin/mail-settings-page', function () {
    return redirect('/admin/mail-settings');
});

Route::post('f/{slug}', [PublicEndpointController::class, 'submit'])
    ->middleware('throttle:endpoint-submissions')
    ->name('forms.endpoint.submit');

Route::middleware(['web', 'guest'])->group(function () {
    Route::get('auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->name('social.redirect');

    Route::get('auth/{provider}/callback', [SocialLoginController::class, 'callback'])
        ->name('social.callback');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('billing/upgrade', [BillingController::class, 'upgrade'])->name('billing.upgrade');
    Route::get('billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::get('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
});

Route::post('api/v1/zoneless/webhook', [BillingController::class, 'webhook'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('zoneless.webhook');
