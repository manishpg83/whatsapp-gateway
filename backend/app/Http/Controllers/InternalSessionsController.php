<?php

namespace App\Http\Controllers;

use App\Models\WhatsappSession;
use Illuminate\Http\JsonResponse;

/**
 * Called by the worker once at boot (see whatsapp-worker's
 * reconnectAll.ts). Tells it which instances SHOULD currently have a
 * live Baileys socket, so it can reconnect them automatically using
 * their already-saved credentials — fixes the gap where a worker restart
 * (including tsx watch auto-reloading on every file save) left the
 * database saying "connected" with no way back short of a manual
 * Disconnect + Reconnect click.
 *
 * Protected by the internal.secret middleware, same as the worker's
 * event webhook — never session auth, since the worker has no browser
 * session.
 */
class InternalSessionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $instanceIds = WhatsappSession::whereIn('status', ['connected', 'connecting', 'qr_pending'])
            ->pluck('instance_id');

        return response()->json(['instance_ids' => $instanceIds]);
    }
}
