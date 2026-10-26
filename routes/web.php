<?php

use App\Http\Controllers\Api\PublicEndpointController;
use App\Http\Controllers\Auth\SocialLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
