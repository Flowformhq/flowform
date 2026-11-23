<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionFileController extends Controller
{
    /**
     * Download an uploaded submission file.
     */
    public function download(Request $request, Submission $submission, int $index): StreamedResponse
    {
        // 1. Authorization: either valid temporary signed URL or authenticated owner/admin
        if ($request->has('signature')) {
            if (! $request->hasValidSignature()) {
                abort(403, 'Invalid or expired file access signature.');
            }
        } else {
            $user = auth()->user();
            if (! $user) {
                abort(403, 'Unauthorized.');
            }

            $isOwner = ($submission->form && $submission->form->user_id === $user->id) || ($submission->user_id === $user->id);
            $isAdmin = (bool) $user->is_admin;

            if (! $isOwner && ! $isAdmin) {
                abort(403, 'Unauthorized.');
            }
        }

        // 2. Locate requested file descriptor
        $files = $submission->getUploadedFiles();
        $file = $files[$index] ?? null;

        if (! $file) {
            abort(404, 'File not found in submission.');
        }

        // 3. Verify file exists on configured disk
        $diskName = config('flowform.uploads.disk', 'local');
        $disk = Storage::disk($diskName);

        if (! $disk->exists($file['path'])) {
            abort(404, 'File not found on storage disk.');
        }

        return $disk->download(
            $file['path'],
            $file['name'] ?? 'download',
            ['Content-Type' => $file['mime'] ?? 'application/octet-stream']
        );
    }
}
