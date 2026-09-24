<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MediaFetchException;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\WhatsappSession;
use App\Services\MediaFetcher;
use App\Services\MessageSender;
use App\Services\PlanLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    public function send(Request $request, MessageSender $sender, PlanLimiter $limiter, MediaFetcher $fetcher): JsonResponse
    {
        /** @var WhatsappSession $whatsappSession set by AuthenticateApiToken */
        $whatsappSession = $request->attributes->get('whatsapp_session');
        /** @var ApiToken $apiToken set by AuthenticateApiToken */
        $apiToken = $request->attributes->get('api_token');

        // Omitted type = "text", so existing text-only callers keep working unchanged.
        $isText = ($request->input('type') ?? 'text') === 'text';
        // A file sent as multipart/form-data, instead of a media_url. (file(),
        // not hasFile(): hasFile() hides uploads that failed, e.g. too big —
        // MediaFetcher::fromUpload() turns those into a clear error instead.)
        $upload = $request->file('media');
        $hasUpload = $upload instanceof UploadedFile;

        $data = $request->validate([
            'instance_id' => ['required', 'uuid'],
            'to' => ['required', 'regex:/^\d{7,15}$/'],
            'type' => ['sometimes', 'in:text,'.implode(',', array_keys(MediaFetcher::RULES))],
            // The text — or, for image/video/document, an optional caption.
            'message' => [Rule::requiredIf($isText), 'nullable', 'string', 'max:4096'],
            // Media needs exactly one of: media_url, or an uploaded `media` file.
            'media_url' => [Rule::requiredIf(! $isText && ! $hasUpload), Rule::prohibitedIf($isText || $hasUpload), 'nullable', 'url', 'max:2048'],
            'media' => [Rule::prohibitedIf($isText)],
            'file_name' => ['nullable', 'string', 'max:200'],
        ], [
            'media_url.required' => 'Send either media_url or a media file when type is not text.',
            'media_url.prohibited' => $isText
                ? 'media_url is only allowed when type is image, video, audio, voice or document.'
                : 'Send either media_url or a media file, not both.',
            'media.prohibited' => 'A media file is only allowed when type is image, video, audio, voice or document.',
        ]);

        $type = $data['type'] ?? 'text';
        // Voice notes and audio can't carry a caption on WhatsApp.
        $body = in_array($type, ['audio', 'voice'], true) ? '' : (string) ($data['message'] ?? '');

        // The token already identifies an instance — the instance_id in
        // the body must match it too (CLAUDE.md §5: defence-in-depth).
        // A mismatch almost certainly just means a caller reused a token
        // against the wrong instance_id, but it's rejected either way.
        if ($data['instance_id'] !== $whatsappSession->instance_id) {
            return response()->json([
                'success' => false,
                'error' => 'instance_id does not match this token',
            ], 422);
        }

        if ($whatsappSession->status !== 'connected') {
            return response()->json(['success' => false, 'error' => 'Instance is not connected'], 422);
        }

        if (! $limiter->canSendMessage($whatsappSession->user)) {
            return response()->json([
                'success' => false,
                'error' => "You've reached your plan's monthly message limit. Upgrade to send more.",
            ], 422);
        }

        $media = null;

        if ($type !== 'text') {
            try {
                $media = $hasUpload
                    ? $fetcher->fromUpload($whatsappSession, $upload, $type, $data['file_name'] ?? null)
                    : $fetcher->fetch($whatsappSession, $data['media_url'], $type, $data['file_name'] ?? null);
            } catch (MediaFetchException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
        }

        $message = $sender->send($whatsappSession, $data['to'], $body, $apiToken, $type, $media);

        // Tells LogRejectedApiRequests this call is already on API Logs as
        // a message row (even if the worker then failed to send it).
        $request->attributes->set('api_message_created', true);

        if ($message->status === 'failed') {
            return response()->json(['success' => false, 'error' => 'Could not send message'], 502);
        }

        return response()->json(['success' => true, 'message_id' => $message->whatsapp_message_id]);
    }

    /**
     * Status of a message this token's instance sent, looked up by the
     * message_id that send() returned. Only ever searches the token's own
     * instance (CLAUDE.md §5), and "someone else's id" gets the exact same
     * 404 as "no such id", so nobody can probe which ids exist. Never
     * returns the body or the internal error text — status/metadata only.
     */
    public function show(Request $request, string $messageId): JsonResponse
    {
        /** @var WhatsappSession $whatsappSession set by AuthenticateApiToken */
        $whatsappSession = $request->attributes->get('whatsapp_session');

        $message = $whatsappSession->messages()
            ->where('direction', 'outgoing')
            ->where('whatsapp_message_id', $messageId)
            ->first();

        if (! $message) {
            return response()->json(['success' => false, 'error' => 'Message not found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => [
                'message_id' => $message->whatsapp_message_id,
                'instance_id' => $whatsappSession->instance_id,
                'direction' => $message->direction,
                'type' => $message->type,
                'to' => $message->to_number,
                'status' => $message->status,
                'delivered_at' => $message->delivered_at?->toIso8601String(),
                'read_at' => $message->read_at?->toIso8601String(),
                'created_at' => $message->created_at->toIso8601String(),
                'updated_at' => $message->updated_at->toIso8601String(),
            ],
        ]);
    }
}
