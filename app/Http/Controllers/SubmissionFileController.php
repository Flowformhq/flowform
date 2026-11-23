<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionFileController extends Controller
{
    public function download(Request $request, Submission $submission, int $index): StreamedResponse
    {
        $user = $request->user();
        $authorized = $request->hasValidSignature()
            || ($user && ($user->is_admin || $submission->form->user_id === $user->id));

        abort_unless($authorized, 403);

        $file = $submission->getUploadedFiles()[$index] ?? null;
        $disk = Storage::disk(config('flowform.uploads.disk'));

        abort_unless($file && $disk->exists($file['path']), 404);

        return $disk->download($file['path'], $file['name'], [
            'Content-Type' => $file['mime'] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
