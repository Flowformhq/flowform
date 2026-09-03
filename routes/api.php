<?php

use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:api-public')->group(function () {
        Route::get('forms', [FormController::class, 'index']);
        Route::get('forms/{uuid}/schema', [FormController::class, 'schema']);
        Route::get('forms/{slug}/by-slug', [FormController::class, 'showBySlug']);
        Route::get('forms/{uuid}', [FormController::class, 'show']);

        // Public / Guest Submissions for active forms
        Route::post('public/submissions', [SubmissionController::class, 'store']);
        Route::get('public/submissions/{uuid}', [SubmissionController::class, 'show']);
        Route::patch('public/submissions/{uuid}', [SubmissionController::class, 'update']);
        Route::post('public/submissions/{uuid}/values', [SubmissionController::class, 'storeValues']);
        Route::post('public/submissions/{uuid}/advance', [SubmissionController::class, 'advance']);
        Route::post('public/submissions/{uuid}/retreat', [SubmissionController::class, 'retreat']);
        Route::get('public/submissions/{uuid}/conditions', [SubmissionController::class, 'conditions']);
    });

    Route::middleware(['auth:sanctum', 'throttle:api-auth'])->group(function () {
        Route::post('submissions', [SubmissionController::class, 'store']);
        Route::get('submissions/{uuid}', [SubmissionController::class, 'show']);
        Route::patch('submissions/{uuid}', [SubmissionController::class, 'update']);
        Route::post('submissions/{uuid}/values', [SubmissionController::class, 'storeValues']);
        Route::post('submissions/{uuid}/advance', [SubmissionController::class, 'advance']);
        Route::post('submissions/{uuid}/retreat', [SubmissionController::class, 'retreat']);
        Route::get('submissions/{uuid}/conditions', [SubmissionController::class, 'conditions']);
    });
});
