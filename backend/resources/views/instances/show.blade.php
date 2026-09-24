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
            <div class="d-flex gap-2">
                <a href="{{ route('api-logs.index', ['instance_id' => $instance->instance_id]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-clock-history me-1"></i>API Logs
                </a>
                <a href="{{ route('instances.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to instances
                </a>
            </div>
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
                    <form method="POST" action="{{ route('instances.send-test-message', $instance) }}" class="row g-2 align-items-end" enctype="multipart/form-data">
                        @csrf
                        <div class="col-md-4">
                            <label for="test-message-to" class="form-label small mb-0">To (digits only, country code first)</label>
                            <input type="text" class="form-control form-control-sm @error('to') is-invalid @enderror"
                                   id="test-message-to" name="to" placeholder="919XXXXXXXXX" value="{{ old('to') }}" required>
                            @error('to')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label for="test-message-type" class="form-label small mb-0">Type</label>
                            <select id="test-message-type" name="type" class="form-select form-select-sm">
                                @foreach (['text' => 'Text', 'image' => 'Image', 'video' => 'Video', 'audio' => 'Audio file', 'voice' => 'Voice note (.ogg)', 'document' => 'Document'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type', 'text') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="test-message-body" class="form-label small mb-0">Message <span class="text-muted">(caption for media)</span></label>
                            <input type="text" class="form-control form-control-sm @error('message') is-invalid @enderror"
                                   id="test-message-body" name="message" placeholder="Hello from the dashboard" value="{{ old('message') }}">
                            @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        {{-- Media: drag & drop / choose a file, or paste a link. Hidden for Text. --}}
                        <div class="col-12" data-media-fields @if (old('type', 'text') === 'text') hidden @endif>
                            <label for="test-message-file" class="media-dropzone d-flex flex-column align-items-center justify-content-center text-center rounded border border-2 border-dashed p-3 mb-2 w-100"
                                   style="border-style: dashed !important; cursor: pointer;" data-dropzone>
                                <i class="bi bi-cloud-arrow-up fs-3 text-primary"></i>
                                <span class="small" data-dropzone-text>
                                    <strong>Drag &amp; drop a file here</strong> or <span class="text-primary text-decoration-underline">choose one from your computer</span>
                                </span>
                                <span class="small text-muted">Max {{ ini_get('upload_max_filesize') }} per upload on this server</span>
                                <input type="file" id="test-message-file" name="media" class="d-none">
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">or link</span>
                                <input type="url" class="form-control @error('media_url') is-invalid @enderror"
                                       id="test-message-media-url" name="media_url" placeholder="https://example.com/photo.jpg" value="{{ old('media_url') }}">
                                @error('media_url')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
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
                    <button class="btn btn-outline-secondary" type="button" data-copy-target="#instance-id-value" title="Copy instance ID">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>

                @if (session('new_token'))
                    <div class="alert alert-warning">
                        <strong>Copy this token now — you won't be able to see it again:</strong>
                        <div class="input-group mt-2">
                            <input type="text" class="form-control font-monospace" value="{{ session('new_token') }}" id="new-token-value" readonly>
                            <button class="btn btn-outline-secondary" type="button" data-copy-target="#new-token-value" title="Copy access token">
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

                @if ($instance->webhook_url)
                    <form method="POST" action="{{ route('instances.webhook.test', $instance) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-send me-1"></i>Send test webhook
                        </button>
                        <span class="text-muted small ms-2">Sends a signed <code>webhook.test</code> event to your URL right now.</span>
                    </form>
                @endif

                <details class="mt-3 small">
                    <summary class="text-primary" style="cursor: pointer;">What does an incoming-message webhook look like?</summary>
                    <p class="mt-2 mb-1">
                        <code>type</code> is one of: <code>text</code>, <code>image</code>, <code>video</code>, <code>voice</code>,
                        <code>audio</code>, <code>document</code>, <code>sticker</code>, <code>location</code>, <code>contact</code>,
                        <code>unsupported</code>. For media, <code>message</code> is the caption (may be empty) and
                        <code>media.url</code> is a download link valid for <strong>24 hours</strong> — save the file on your side if you need it later.
                        <code>media</code> is <code>null</code> for text, location and contact messages.
                    </p>
<pre class="bg-light rounded p-3 mb-0"><code>{
  "event": "message.received",
  "instance_id": "{{ $instance->instance_id }}",
  "from": "919876543210",
  "type": "image",
  "message": "Here is the photo",
  "message_id": "3EB0A1B2C3D4E5F6",
  "timestamp": "2026-09-24T10:15:03.000Z",
  "media": {
    "status": "stored",
    "mime_type": "image/jpeg",
    "file_name": null,
    "size": 184223,
    "url": "{{ url('/media/123') }}?expires=...&amp;signature=..."
  }
}</code></pre>
                    <p class="text-muted mt-2 mb-0">
                        <code>media.status</code> is <code>stored</code>, <code>too_large</code> (over 100 MB, not downloaded) or <code>failed</code>
                        — <code>url</code> is only set when it's <code>stored</code>.
                    </p>
                </details>
            </div>

            {{-- Delivery log --}}
            <div class="card-body border-top">
                <div class="fw-semibold small text-uppercase text-muted mb-2">Recent deliveries</div>
                @if ($webhookDeliveries->isEmpty())
                    <p class="text-muted small mb-0">No webhooks sent yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Event</th>
                                    <th>Status</th>
                                    <th>HTTP</th>
                                    <th>Attempts</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($webhookDeliveries as $delivery)
                                    @php
                                        $label = $delivery->statusLabel();
                                        $color = match ($label) {
                                            'Delivered' => 'success',
                                            'Failed' => 'danger',
                                            'Retrying' => 'warning',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="text-nowrap small">{{ $delivery->created_at->format('Y-m-d H:i:s') }}</td>
                                        <td><code class="small">{{ $delivery->event }}</code></td>
                                        <td><span class="badge rounded-pill text-bg-{{ $color }}">{{ $label }}</span></td>
                                        <td class="small">{{ $delivery->response_status ?? '—' }}</td>
                                        <td class="small">{{ $delivery->attempts }}</td>
                                        <td class="small text-muted text-truncate" style="max-width: 260px;" title="{{ $delivery->error }}">{{ $delivery->error ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($webhookDeliveries->contains(fn ($d) => $d->statusLabel() === 'Queued'))
                        <p class="text-muted small mb-0 mt-2">
                            <i class="bi bi-info-circle me-1"></i>"Queued" webhooks are waiting to be sent — refresh in a few seconds.
                        </p>
                    @endif
                @endif
            </div>
        </div>

        {{-- Recent messages --}}
        <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3 border-bottom">
                <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
                    <i class="bi bi-chat-left-text"></i>
                </div>
                <div>
                    <div class="fw-semibold">Recent messages</div>
                    <div class="text-muted small">
                        <i class="bi bi-arrow-up-short text-primary"></i>{{ $sentCount }} sent
                        @if ($failedCount > 0)
                            &middot; <span class="text-danger">{{ $failedCount }} failed</span>
                        @endif
                        &middot; <i class="bi bi-arrow-down-short text-primary"></i>{{ $receivedCount }} received
                    </div>
                </div>
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
                                    <td>@include('messages._content', ['message' => $message, 'compact' => true])</td>
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
// "Send a test message": show the media box for media types, and handle
// drag & drop / file choice (pre-selecting the Type from the file).
(function () {
    const typeSelect = document.getElementById('test-message-type');
    const mediaFields = document.querySelector('[data-media-fields]');
    const dropzone = document.querySelector('[data-dropzone]');
    const fileInput = document.getElementById('test-message-file');

    if (!typeSelect || !mediaFields || !dropzone || !fileInput) {
        return; // the form only exists while the instance is connected
    }

    const dropText = dropzone.querySelector('[data-dropzone-text]');
    const originalText = dropText.innerHTML;

    typeSelect.addEventListener('change', () => {
        mediaFields.hidden = typeSelect.value === 'text';
    });

    function typeForFile(file) {
        if (file.type.startsWith('image/')) return 'image';
        if (file.type.startsWith('video/')) return 'video';
        if (file.type.startsWith('audio/')) return file.type === 'audio/ogg' ? 'voice' : 'audio';
        return 'document';
    }

    function showChosenFile() {
        const file = fileInput.files[0];

        if (!file) {
            dropText.innerHTML = originalText;
            return;
        }

        const sizeMb = (file.size / 1024 / 1024).toFixed(1);
        dropText.textContent = `✓ ${file.name} (${sizeMb} MB) — click to change`;
        typeSelect.value = typeForFile(file);
        mediaFields.hidden = false;
    }

    fileInput.addEventListener('change', showChosenFile);

    ['dragenter', 'dragover'].forEach((name) => dropzone.addEventListener(name, (event) => {
        event.preventDefault();
        dropzone.classList.add('bg-wa-light', 'border-primary');
    }));

    ['dragleave', 'drop'].forEach((name) => dropzone.addEventListener(name, (event) => {
        event.preventDefault();
        dropzone.classList.remove('bg-wa-light', 'border-primary');
    }));

    dropzone.addEventListener('drop', (event) => {
        if (event.dataTransfer.files.length) {
            fileInput.files = event.dataTransfer.files;
            showChosenFile();
        }
    });
})();
</script>

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
