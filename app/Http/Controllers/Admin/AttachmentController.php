<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Streams a receipt from the private disk. Files are never served from a
     * public path, so this route is the only way to reach one - and it sits
     * behind the admin guard.
     */
    public function show(TransactionAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        // Images and PDFs preview in the browser; anything else downloads.
        $inline = $attachment->isImage() || $attachment->mime_type === 'application/pdf';

        return $disk->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream'],
            $inline ? 'inline' : 'attachment',
        );
    }

    public function download(TransactionAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name);
    }

    public function destroy(TransactionAttachment $attachment): RedirectResponse
    {
        $attachment->deleteWithFile();

        return back()->with('success', 'Attachment removed.');
    }
}
