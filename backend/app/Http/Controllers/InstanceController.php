<?php

namespace App\Http\Controllers;

use App\Models\WhatsappSession;
use App\Services\WorkerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class InstanceController extends Controller
{
    public function index(Request $request): View
    {
        $instances = $request->user()->whatsappSessions()->latest()->get();

        return view('instances.index', ['instances' => $instances]);
    }

    public function create(): View
    {
        return view('instances.create');
    }

    public function store(Request $request, WorkerClient $worker): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $instance = $request->user()->whatsappSessions()->create([
            'name' => $data['name'],
            'status' => 'connecting',
        ]);

        try {
            $worker->startSession($instance->instance_id);
        } catch (Throwable $e) {
            Log::error('Worker unreachable while starting a session', [
                'instance_id' => $instance->instance_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('instances.show', $instance)
                ->with('error', 'Could not reach the WhatsApp worker. Is it running? Use "Reconnect" to retry.');
        }

        return redirect()->route('instances.show', $instance);
    }

    public function show(Request $request, string $instance): View
    {
        return view('instances.show', ['instance' => $this->findOwnedInstance($request, $instance)]);
    }

    /**
     * Polled by the browser every 2-3 seconds on the show page.
     */
    public function status(Request $request, string $instance): JsonResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        return response()->json([
            'status' => $whatsappSession->status,
            'qr_code' => $whatsappSession->qr_code,
            'phone_number' => $whatsappSession->phone_number,
        ]);
    }

    public function destroy(Request $request, string $instance, WorkerClient $worker): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        try {
            $worker->stopSession($whatsappSession->instance_id);
        } catch (Throwable $e) {
            Log::error('Worker unreachable while stopping a session', [
                'instance_id' => $whatsappSession->instance_id,
                'error' => $e->getMessage(),
            ]);
        }

        $whatsappSession->update(['status' => 'disconnected']);

        return redirect()->route('instances.index')->with('status', 'Instance disconnected.');
    }

    public function reconnect(Request $request, string $instance, WorkerClient $worker): RedirectResponse
    {
        $whatsappSession = $this->findOwnedInstance($request, $instance);

        $whatsappSession->update(['status' => 'connecting', 'qr_code' => null]);

        try {
            $worker->startSession($whatsappSession->instance_id);
        } catch (Throwable $e) {
            Log::error('Worker unreachable while reconnecting a session', [
                'instance_id' => $whatsappSession->instance_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('instances.show', $whatsappSession)
                ->with('error', 'Could not reach the WhatsApp worker. Is it running?');
        }

        return redirect()->route('instances.show', $whatsappSession);
    }

    /**
     * Looks up an instance scoped to the current user (query-level
     * defence) and then double-checks ownership explicitly (CLAUDE.md §5:
     * scope in the query AND an explicit check). A UUID belonging to
     * another user, or one that doesn't exist at all, both 404 — never
     * confirm to a caller that someone else's instance exists.
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
