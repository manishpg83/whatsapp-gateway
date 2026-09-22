<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappSession;
use App\Services\WorkerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class MessageController extends Controller
{
    public function send(Request $request, WorkerClient $worker): JsonResponse
    {
        /** @var WhatsappSession $whatsappSession set by AuthenticateApiToken */
        $whatsappSession = $request->attributes->get('whatsapp_session');

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

        $message = $whatsappSession->messages()->create([
            'direction' => 'outgoing',
            'to_number' => $data['to'],
            'body' => $data['message'],
            'status' => 'pending',
        ]);

        try {
            $messageId = $worker->sendMessage($whatsappSession->instance_id, $data['to'], $data['message']);
        } catch (Throwable $e) {
            Log::error('Worker failed to send a message', [
                'instance_id' => $whatsappSession->instance_id,
                'error' => $e->getMessage(),
            ]);

            $message->update(['status' => 'failed', 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => 'Could not send message'], 502);
        }

        $message->update(['status' => 'sent', 'whatsapp_message_id' => $messageId]);

        return response()->json(['success' => true, 'message_id' => $messageId]);
    }
}
