@extends('layouts.app')

@section('title', $instance->name)

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">{{ $instance->name }}</h1>
            <a href="{{ route('instances.index') }}" class="btn btn-sm btn-outline-secondary">Back to instances</a>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body text-center py-5" id="instance-status" data-instance-status="{{ $instance->status }}">

                @if ($instance->status === 'connected')
                    <div class="text-success mb-3">
                        <div class="display-6">✓ Connected</div>
                    </div>
                    <p class="text-muted">{{ $instance->phone_number }}</p>

                    <form method="POST" action="{{ route('instances.destroy', $instance) }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">Disconnect</button>
                    </form>
                @elseif (in_array($instance->status, ['disconnected', 'logged_out']))
                    <div class="text-muted mb-3">
                        <div class="display-6">Not connected</div>
                        @if ($instance->last_disconnect_reason)
                            <p class="small mt-2">{{ $instance->last_disconnect_reason }}</p>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('instances.reconnect', $instance) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Reconnect</button>
                    </form>
                @else
                    {{-- connecting / qr_pending --}}
                    <p class="text-muted mb-3">Scan this QR code with WhatsApp on your phone:<br>
                        <small>Settings &rarr; Linked Devices &rarr; Link a Device</small>
                    </p>

                    <div id="qr-holder">
                        @if ($instance->qr_code)
                            <img src="{{ $instance->qr_code }}" alt="WhatsApp QR code" id="qr-image" class="img-fluid" style="max-width: 280px;">
                        @else
                            <p class="text-muted">Waiting for QR code&hellip;</p>
                        @endif
                    </div>
                @endif

            </div>
        </div>

        {{-- API credentials --}}
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">API credentials</div>
            <div class="card-body">

                @if (session('new_token'))
                    <div class="alert alert-warning">
                        <strong>Copy this token now — you won't be able to see it again:</strong>
                        <div class="input-group mt-2">
                            <input type="text" class="form-control font-monospace" value="{{ session('new_token') }}" id="new-token-value" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('new-token-value').value)">Copy</button>
                        </div>
                    </div>
                @endif

                @if ($instance->apiTokens->isEmpty())
                    <p class="text-muted mb-3">No tokens yet.</p>
                @else
                    <table class="table table-sm align-middle mb-3">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Token</th>
                                <th>Created</th>
                                <th>Last used</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($instance->apiTokens as $token)
                                <tr>
                                    <td>{{ $token->name }}</td>
                                    <td class="font-monospace">{{ $token->token_prefix }}&hellip;</td>
                                    <td>{{ $token->created_at->format('M j, Y') }}</td>
                                    <td>{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td class="text-end">
                                        @if ($token->revoked_at)
                                            <span class="badge text-bg-secondary">Revoked</span>
                                        @else
                                            <form method="POST" action="{{ route('instances.tokens.destroy', [$instance, $token]) }}"
                                                  onsubmit="return confirm('Revoke this token? Anything using it will stop working immediately.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if ($instance->status === 'connected')
                    <form method="POST" action="{{ route('instances.tokens.store', $instance) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-auto">
                            <label for="token-name" class="form-label small mb-0">New token name</label>
                            <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                   id="token-name" name="name" placeholder="e.g. Production server" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">Generate token</button>
                        </div>
                    </form>
                @else
                    <p class="text-muted small mb-0">Connect this instance to generate an API token.</p>
                @endif

            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const statusBox = document.getElementById('instance-status');
    let currentStatus = statusBox.dataset.instanceStatus;

    // Only the connecting/qr_pending states change on their own — once
    // connected/disconnected, the page's Disconnect/Reconnect buttons take
    // over, so there's nothing left to poll for.
    if (currentStatus !== 'connecting' && currentStatus !== 'qr_pending') {
        return;
    }

    const statusUrl = @json(route('instances.status', $instance));

    const poll = setInterval(async () => {
        let data;
        try {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            data = await response.json();
        } catch (e) {
            return; // network hiccup — just try again next tick
        }

        if (data.status !== currentStatus) {
            // Status moved to a terminal state (connected/disconnected/
            // logged_out) — reload so the page renders the right buttons.
            clearInterval(poll);
            window.location.reload();
            return;
        }

        if (data.qr_code) {
            const holder = document.getElementById('qr-holder');
            holder.innerHTML = '<img src="' + data.qr_code + '" alt="WhatsApp QR code" id="qr-image" class="img-fluid" style="max-width: 280px;">';
        }
    }, 2500);
})();
</script>
@endsection
