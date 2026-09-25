<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappSession;
use App\Services\WorkerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class NumberController extends Controller
{
    // Kept small (plus its own rate limit) so this can't be used to scrape
    // which numbers are on WhatsApp. The worker enforces the same cap.
    public const MAX_NUMBERS = 20;

    /**
     * "Is this number on WhatsApp?" — lets callers check before sending.
     * Doesn't send anything and doesn't count toward the message limit.
     */
    public function check(Request $request, WorkerClient $worker): JsonResponse
    {
        /** @var WhatsappSession $whatsappSession set by AuthenticateApiToken */
        $whatsappSession = $request->attributes->get('whatsapp_session');

        $data = $request->validate([
            'instance_id' => ['required', 'uuid'],
            'numbers' => ['required', 'array', 'min:1', 'max:'.self::MAX_NUMBERS],
            'numbers.*' => ['required', 'string', 'regex:/^\d{7,15}$/'],
        ], [
            'numbers.max' => 'You can check at most '.self::MAX_NUMBERS.' numbers per request.',
            'numbers.*.regex' => 'Each number must be digits only with country code, e.g. 919876543210.',
        ]);

        // Same defence-in-depth as sending (CLAUDE.md §5).
        if ($data['instance_id'] !== $whatsappSession->instance_id) {
            return response()->json(['success' => false, 'error' => 'instance_id does not match this token'], 422);
        }

        if ($whatsappSession->status !== 'connected') {
            return response()->json(['success' => false, 'error' => 'Instance is not connected'], 422);
        }

        try {
            $results = $worker->checkNumbers($whatsappSession->instance_id, array_values(array_unique($data['numbers'])));
        } catch (Throwable $e) {
            Log::error('Number check failed', [
                'instance_id' => $whatsappSession->instance_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'Could not check numbers right now'], 502);
        }

        return response()->json([
            'success' => true,
            'results' => array_map(fn (array $result) => [
                'number' => $result['number'],
                'on_whatsapp' => (bool) $result['exists'],
                'whatsapp_number' => $result['whatsapp_number'],
            ], $results),
        ]);
    }
}
