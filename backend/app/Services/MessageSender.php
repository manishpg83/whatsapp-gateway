<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\Message;
use App\Models\WhatsappSession;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared by the public API (Api\MessageController) and the dashboard's
 * own "send a test message" button (InstanceController) — both need the
 * exact same steps (create a pending row, call the worker, mark it
 * sent/failed) and should never be allowed to drift apart.
 */
class MessageSender
{
    public function __construct(protected WorkerClient $worker) {}

    /**
     * Creates a pending message row, attempts to send it, and updates the
     * row to sent/failed either way. Returns the message rather than
     * throwing — "the worker couldn't send it" is an expected, common
     * outcome here (worker not running, bad number, etc.), not a bug, so
     * callers check $message->status instead of catching an exception.
     *
     * $apiToken is only ever passed by the real public API — the
     * dashboard's own "send a test message" button has no token, so its
     * messages stay untagged and don't show up on the API Logs page
     * (there's no real HTTP request/response to log for those).
     *
     * For media, $body is the caption ("" for none) and $media is what
     * MediaFetcher::fetch() returned (the file is already on disk).
     *
     * @param  array{path: string, mime_type: string, file_name: string|null, size: int}|null  $media
     */
    public function send(
        WhatsappSession $session,
        string $to,
        string $body,
        ?ApiToken $apiToken = null,
        string $type = 'text',
        ?array $media = null,
    ): Message {
        $message = $session->messages()->create([
            'api_token_id' => $apiToken?->id,
            'direction' => 'outgoing',
            'type' => $type,
            'to_number' => $to,
            'body' => $body,
            'status' => 'pending',
            'media_status' => $media ? 'stored' : null,
            'media_path' => $media['path'] ?? null,
            'media_mime_type' => $media['mime_type'] ?? null,
            'media_file_name' => $media['file_name'] ?? null,
            'media_size' => $media['size'] ?? null,
        ]);

        try {
            $messageId = $this->worker->sendMessage($session->instance_id, $to, $body, $type, $media);
            $message->update(['status' => 'sent', 'whatsapp_message_id' => $messageId]);
        } catch (Throwable $e) {
            Log::error('Worker failed to send a message', [
                'instance_id' => $session->instance_id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            $message->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $message->refresh();
    }
}
