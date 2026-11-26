<?php

use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\McpController;
use App\Http\Controllers\Api\PublicEndpointController;
use App\Http\Controllers\Api\SubmissionController;
use App\Support\TokenAbility;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:api-public')->group(function () {
        Route::get('forms', [FormController::class, 'index']);
        Route::get('forms/{uuid}/schema', [FormController::class, 'schema']);
        Route::get('forms/{slug}/by-slug', [FormController::class, 'showBySlug']);
        Route::get('forms/{uuid}', [FormController::class, 'show']);

        // Headless Endpoint Form Submission
        Route::post('f/{slug}', [PublicEndpointController::class, 'submit'])
            ->middleware('throttle:endpoint-submissions')
            ->name('api.forms.endpoint.submit');

        // Public Compliance DPA & Safeguards
        Route::get('compliance/dpa', [ComplianceController::class, 'dpa'])
            ->name('api.compliance.dpa');

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
        Route::post('mcp', [McpController::class, 'handle'])
            ->name('api.mcp');

        Route::middleware('ability:'.TokenAbility::SubmissionsRead->value)->group(function () {
            Route::get('submissions/{uuid}', [SubmissionController::class, 'show']);
            Route::get('submissions/{uuid}/conditions', [SubmissionController::class, 'conditions']);
        });

        Route::middleware('ability:'.TokenAbility::SubmissionsWrite->value)->group(function () {
            Route::post('submissions', [SubmissionController::class, 'store']);
            Route::patch('submissions/{uuid}', [SubmissionController::class, 'update']);
            Route::post('submissions/{uuid}/values', [SubmissionController::class, 'storeValues']);
            Route::post('submissions/{uuid}/advance', [SubmissionController::class, 'advance']);
            Route::post('submissions/{uuid}/retreat', [SubmissionController::class, 'retreat']);
        });

        // GDPR & HIPAA Compliance API
        Route::middleware('abilities:'.implode(',', TokenAbility::values()))->group(function () {
            Route::get('compliance/gdpr/export', [ComplianceController::class, 'export'])
                ->name('api.compliance.gdpr.export');
            Route::post('compliance/gdpr/erasure', [ComplianceController::class, 'erasure'])
                ->name('api.compliance.gdpr.erasure');
        });
    });
});
