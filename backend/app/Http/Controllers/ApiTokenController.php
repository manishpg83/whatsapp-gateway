<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\WhatsappSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function store(Request $request, string $instance): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        abort_unless(
            $whatsappSession->status === 'connected',
            422,
            'Connect this instance before generating an API token.'
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $result = ApiToken::generateFor($whatsappSession, $data['name']);

        return redirect()->route('instances.show', $whatsappSession)
            ->with('new_token', $result['plainText'])
            ->with('status', 'Token generated. Copy it now — you will not be able to see it again.');
    }

    public function destroy(Request $request, string $instance, ApiToken $token): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        // Defence-in-depth, same pattern as instance ownership (CLAUDE.md
        // §5): the token must actually belong to THIS instance.
        abort_unless($token->whatsapp_session_id === $whatsappSession->id, 404);

        $token->update(['revoked_at' => now()]);

        return redirect()->route('instances.show', $whatsappSession)
            ->with('status', 'Token revoked.');
    }

    /**
     * Same ownership pattern as InstanceController: scope in the query,
     * then double-check explicitly.
     */
    protected function findOwnedInstance(Request $request, string $instanceId): WhatsappSession
    {
        $whatsappSession = $request->user()->whatsappSessions()
            ->where('instance_id', $instanceId)
            ->firstOrFail();

        abort_unless($whatsappSession->user_id === $request->user()->id, 404);

        return $whatsappSession;
    }
}
