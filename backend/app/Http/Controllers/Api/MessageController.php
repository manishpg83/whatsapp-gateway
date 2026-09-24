<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\WhatsappSession;
use App\Services\MessageSender;
use App\Services\PlanLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function send(Request $request, MessageSender $sender, PlanLimiter $limiter): JsonResponse
    {
        /** @var WhatsappSession $whatsappSession set by AuthenticateApiToken */
        $whatsappSession = $request->attributes->get('whatsapp_session');
        /** @var ApiToken $apiToken set by AuthenticateApiToken */
        $apiToken = $request->attributes->get('api_token');

        $data = $request->validate([
            'instance_id' => ['required', 'uuid'],
            'to' => ['required', 'regex:/^\d{7,15}$/'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

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

        $message = $sender->send($whatsappSession, $data['to'], $data['message'], $apiToken);

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
                'to' => $message->to_number,
                'status' => $message->status,
                'created_at' => $message->created_at->toIso8601String(),
                'updated_at' => $message->updated_at->toIso8601String(),
            ],
        ]);
    }
}
