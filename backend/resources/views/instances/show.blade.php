@extends('layouts.app')

@section('title', $instance->name)

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center gap-2">
                <span class="bg-wa-light text-primary rounded-3 d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                    <i class="bi bi-hdd-stack"></i>
                </span>
                <h1 class="h3 mb-0">{{ $instance->name }}</h1>
            </div>
            <a href="{{ route('instances.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to instances
            </a>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body text-center py-5" id="instance-status" data-instance-status="{{ $instance->status }}">

                @if ($instance->status === 'connected')
                    <div class="bg-wa-light text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3 fs-1" style="width: 88px; height: 88px;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="fs-4 fw-semibold text-primary mb-1">Connected</div>
                    <p class="text-muted">{{ $instance->phone_number }}</p>

                    <form method="POST" action="{{ route('instances.destroy', $instance) }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">Disconnect</button>
                    </form>
                @elseif (in_array($instance->status, ['disconnected', 'logged_out']))
                    <div class="bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center mb-3 fs-1" style="width: 88px; height: 88px;">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div class="fs-4 fw-semibold text-muted mb-1">Not connected</div>
                    @if ($instance->last_disconnect_reason)
                        <p class="small text-muted mb-3">{{ $instance->last_disconnect_reason }}</p>
                    @endif

                    <form method="POST" action="{{ route('instances.reconnect', $instance) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-primary">Reconnect</button>
                    </form>
                @else
                    {{-- connecting / qr_pending --}}
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 fs-1" style="width: 64px; height: 64px; background-color: var(--wa-info-light); color: var(--wa-info);">
                        <i class="bi bi-qr-code"></i>
                    </div>
                    <p class="text-muted mb-3">Scan this QR code with WhatsApp on your phone:<br>
                        <small>Settings &rarr; Linked Devices &rarr; Link a Device</small>
                    </p>

                    <div id="qr-holder">
                        @if ($instance->qr_code)
                            <img src="{{ $instance->qr_code }}" alt="WhatsApp QR code" id="qr-image" class="img-fluid rounded-3 border" style="max-width: 280px;">
                        @else
                            <p class="text-muted">Waiting for QR code&hellip;</p>
                        @endif
                    </div>
                @endif

            </div>
        </div>

        @if ($instance->status === 'connected')
            {{-- Send a test message --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body d-flex align-items-center gap-3 border-bottom">
                    <div class="rounded-circle p-2 fs-4 lh-1" style="background-color: var(--wa-info-light); color: var(--wa-info);">
                        <i class="bi bi-send"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Send a test message</div>
                        <div class="text-muted small">This is exactly what the public API does — a quick way to try sending without curl/Postman.</div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('instances.send-test-message', $instance) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label for="test-message-to" class="form-label small mb-0">To (digits only, country code first)</label>
                            <input type="text" class="form-control form-control-sm @error('to') is-invalid @enderror"
                                   id="test-message-to" name="to" placeholder="919XXXXXXXXX" value="{{ old('to') }}" required>
                            @error('to')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="test-message-body" class="form-label small mb-0">Message</label>
                            <input type="text" class="form-control form-control-sm @error('message') is-invalid @enderror"
                                   id="test-message-body" name="message" placeholder="Hello from the dashboard" value="{{ old('message') }}" required>
                            @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Send</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- API credentials --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex align-items-center gap-3 border-bottom">
                <div class="rounded-circle p-2 fs-4 lh-1" style="background-color: var(--wa-purple-light); color: var(--wa-purple);">
                    <i class="bi bi-key"></i>
                </div>
                <div>
                    <div class="fw-semibold">API credentials</div>
                    <div class="text-muted small">Use these to authenticate your API requests.</div>
                </div>
            </div>
            <div class="card-body">

                <label for="instance-id-value" class="form-label small mb-0">instance_id</label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control font-monospace" value="{{ $instance->instance_id }}" id="instance-id-value" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('instance-id-value').value)">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>

                @if (session('new_token'))
                    <div class="alert alert-warning">
                        <strong>Copy this token now — you won't be able to see it again:</strong>
                        <div class="input-group mt-2">
                            <input type="text" class="form-control font-monospace" value="{{ session('new_token') }}" id="new-token-value" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('new-token-value').value)">
                                <i class="bi bi-clipboard"></i>
                            </button>
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
                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Generate token</button>
                        </div>
                    </form>
                @else
                    <p class="text-muted small mb-0">Connect this instance to generate an API token.</p>
                @endif

            </div>
        </div>

        {{-- Webhook --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex align-items-center gap-3 border-bottom">
                <div class="rounded-circle p-2 fs-4 lh-1" style="background-color: var(--wa-info-light); color: var(--wa-info);">
                    <i class="bi bi-link-45deg"></i>
                </div>
                <div>
                    <div class="fw-semibold">Webhook</div>
                    <div class="text-muted small">When someone messages this WhatsApp number, we'll forward it here as JSON, signed with the secret below.</div>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('instances.webhook.update', $instance) }}" class="row g-2 align-items-end mb-3">
                    @csrf
                    <div class="col-md-8">
                        <label for="webhook-url" class="form-label small mb-0">Webhook URL</label>
                        <input type="url" class="form-control form-control-sm @error('webhook_url') is-invalid @enderror"
                               id="webhook-url" name="webhook_url" placeholder="https://your-app.example.com/webhooks/whatsapp"
                               value="{{ old('webhook_url', $instance->webhook_url) }}">
                        @error('webhook_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                    </div>
                    @if ($instance->webhook_url)
                        <div class="col-auto">
                            <button type="submit" name="webhook_url" value="" class="btn btn-sm btn-outline-danger">Clear</button>
                        </div>
                    @endif
                </form>

                @if ($instance->webhook_secret)
                    <p class="small text-muted mb-0">
                        Signing secret: <code>{{ $instance->webhook_secret }}</code><br>
                        Each request carries an <code>X-Webhook-Signature: sha256=&lt;hmac&gt;</code> header — HMAC-SHA256 of the raw JSON body using this secret.
                    </p>
                @endif
            </div>
        </div>

        {{-- Recent messages --}}
        <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3 border-bottom">
                <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
                    <i class="bi bi-chat-left-text"></i>
                </div>
                <div class="fw-semibold">Recent messages</div>
            </div>
            <div class="card-body">
                @if ($messages->isEmpty())
                    <p class="text-muted mb-0">No messages yet.</p>
                @else
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Number</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($messages as $message)
                                <tr>
                                    <td>
                                        @if ($message->direction === 'incoming')
                                            <span class="badge text-bg-info">In</span>
                                        @else
                                            <span class="badge text-bg-secondary">Out</span>
                                        @endif
                                    </td>
                                    <td>{{ $message->direction === 'incoming' ? $message->from_number : $message->to_number }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($message->body, 60) }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $message->status === 'failed' ? 'danger' : 'light text-dark' }}">{{ $message->status }}</span>
                                    </td>
                                    <td>{{ $message->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
            holder.innerHTML = '<img src="' + data.qr_code + '" alt="WhatsApp QR code" id="qr-image" class="img-fluid rounded-3 border" style="max-width: 280px;">';
        }
    }, 2500);
})();
</script>
@endsection
