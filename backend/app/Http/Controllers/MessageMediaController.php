<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves received media files (images, voice notes, documents, ...) from
 * the private whatsapp_media disk. Two ways in:
 *
 * - show():   the dashboard — logged in, and only the instance's owner.
 * - signed(): the 24-hour link sent in webhooks — no login, but the URL
 *             carries a signature Laravel checks (the `signed` middleware),
 *             so it can't be guessed or altered.
 */
class MessageMediaController extends Controller
{
    public function show(Request $request, Message $message): StreamedResponse
    {
        // Same 404 for "not yours" and "doesn't exist" (CLAUDE.md §5).
        abort_unless($message->whatsappSession?->user_id === $request->user()->id, 404);

        return $this->serve($request, $message);
    }

    public function signed(Request $request, Message $message): StreamedResponse
    {
        return $this->serve($request, $message);
    }

    private function serve(Request $request, Message $message): StreamedResponse
    {
        abort_unless($message->hasStoredMedia(), 404);

        // Only plain images/audio/video are shown in the browser; everything
        // else (PDF, HTML, SVG, ...) is forced to download, so a file someone
        // sent can never run as a web page on our domain. ?download=1 forces
        // a download for inline types too.
        $inline = $message->mediaIsInline() && ! $request->boolean('download');

        return Storage::disk('whatsapp_media')->response(
            $message->media_path,
            $message->mediaDownloadName(),
            [
                'Content-Type' => $inline ? $message->media_mime_type : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                // Even if a browser did try to render it as a page: no scripts.
                'Content-Security-Policy' => 'sandbox',
                'Cache-Control' => 'private, max-age=3600',
            ],
            $inline ? 'inline' : 'attachment'
        );
    }
}
