@extends('layouts.app')

@section('title', $instance->name)

@section('content')
@php
    $isConnected = $instance->status === 'connected';
    $isWaiting = in_array($instance->status, ['connecting', 'qr_pending'], true);
    // A kept phone number means the device is still linked (Logout clears
    // it), so Reconnect goes straight back in — no QR steps to show.
    $isResuming = $instance->status === 'connecting' && $instance->phone_number;
    $lastDelivery = $webhookDeliveries->first();
@endphp

<div class="row justify-content-center">
    <div class="col-xl-11">

        {{-- Page header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="bg-wa-light text-primary rounded-3 d-inline-flex align-items-center justify-content-center flex-shrink-0 fs-5" style="width: 44px; height: 44px;">
                    <i class="bi bi-list-ul"></i>
                </span>
                <div>
                    <h1 class="h3 mb-0">{{ $instance->name }}</h1>
                    <div class="text-muted small font-monospace">{{ $instance->instance_id }}</div>
                </div>
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

        {{-- Status banner --}}
        <div id="instance-status" data-instance-status="{{ $instance->status }}"
             class="card shadow-sm mb-4 overflow-hidden {{ $isConnected ? 'instance-hero' : '' }}">
            <div class="card-body p-4 p-md-5">
                @if ($isConnected)
                    <div class="d-flex flex-column flex-md-row align-items-md-center gap-4">
                        <div class="instance-hero-icon"><span class="inner"><i class="bi bi-check-lg"></i></span></div>
                        <div class="flex-grow-1">
                            <div class="fs-3 fw-semibold text-primary mb-1">Connected</div>
                            <div class="text-muted mb-2">
                                Your WhatsApp is ready to use &middot; <span class="fw-semibold text-body">{{ $instance->phone_number }}</span>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                <span class="badge rounded-pill text-bg-primary"><i class="bi bi-circle-fill me-1" style="font-size: .5rem; vertical-align: middle;"></i>Active</span>
                                @if ($instance->connected_at)
                                    <span class="small text-muted">since {{ $instance->connected_at->format('M j, Y H:i') }}</span>
                                @endif
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="stat-chip"><i class="bi bi-arrow-up-right text-primary me-1"></i>{{ $sentCount }} sent</span>
                                <span class="stat-chip"><i class="bi bi-arrow-down-left text-info me-1"></i>{{ $receivedCount }} received</span>
                                @if ($failedCount > 0)
                                    <span class="stat-chip text-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ $failedCount }} failed</span>
                                @endif
                            </div>
                        </div>
                        <div class="d-none d-lg-block position-relative hero-illustration me-3">
                            <span class="hero-blob hero-blob-1"></span>
                            <span class="hero-blob hero-blob-2"></span>
                            <div class="position-absolute top-50 start-50 translate-middle">
                                <div class="hero-phone"><i class="bi bi-whatsapp fs-1"></i></div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-self-md-start">
                            <form method="POST" action="{{ route('instances.disconnect', $instance) }}"
                                  onsubmit="return confirm('Disconnect this WhatsApp number? It stays linked, so Reconnect will not need a QR code.');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Go offline but stay linked"><i class="bi bi-pause-circle me-1"></i>Disconnect</button>
                            </form>
                            <form method="POST" action="{{ route('instances.destroy', $instance) }}"
                                  onsubmit="return confirm('Log out this WhatsApp number? The device is unlinked and you will need to scan a new QR code to reconnect.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Unlink the device completely"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
                            </form>
                        </div>
                    </div>
                @elseif (! $isWaiting)
                    <div class="d-flex flex-column flex-md-row align-items-md-center gap-4">
                        <div class="instance-hero-icon" style="box-shadow: 0 0 0 10px rgba(108, 117, 125, 0.08);">
                            <span class="inner bg-secondary"><i class="bi bi-x-lg"></i></span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fs-3 fw-semibold text-muted mb-1">Not connected</div>
                            @if ($instance->last_disconnect_reason)
                                <p class="small text-muted mb-0">{{ $instance->last_disconnect_reason }}</p>
                            @else
                                <p class="small text-muted mb-0">Reconnect to get a new QR code and link your phone again.</p>
                            @endif
                        </div>
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('instances.reconnect', $instance) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Reconnect</button>
                            </form>
                            @if ($instance->status === 'disconnected')
                                <form method="POST" action="{{ route('instances.destroy', $instance) }}"
                                      onsubmit="return confirm('Log out this WhatsApp number? The device is unlinked and you will need to scan a new QR code to reconnect.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @elseif ($isResuming)
                    <div class="d-flex flex-column flex-md-row align-items-md-center gap-4">
                        <div class="instance-hero-icon"><span class="inner"><span class="spinner-border spinner-border-sm"></span></span></div>
                        <div class="flex-grow-1">
                            <div class="fs-3 fw-semibold mb-1">Reconnecting&hellip;</div>
                            <p class="small text-muted mb-0">Going back online as {{ $instance->phone_number }}. No QR code needed.</p>
                            @if ($instance->last_disconnect_reason)
                                <p class="small text-muted mb-0">{{ $instance->last_disconnect_reason }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('instances.disconnect', $instance) }}" class="align-self-md-start">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Stop trying and stay offline"><i class="bi bi-pause-circle me-1"></i>Disconnect</button>
                        </form>
                    </div>
                @else
                    {{-- connecting / qr_pending --}}
                    <div class="row align-items-center g-4">
                        <div class="col-md-6">
                            <div class="section-icon mb-3" style="background-color: var(--wa-info-light); color: var(--wa-info);">
                                <i class="bi bi-qr-code"></i>
                            </div>
                            <div class="fs-4 fw-semibold mb-2">Link your WhatsApp</div>
                            <ol class="text-muted mb-0 ps-3">
                                <li>Open WhatsApp on your phone</li>
                                <li>Go to <strong>Settings &rarr; Linked Devices</strong></li>
                                <li>Tap <strong>Link a Device</strong> and scan this code</li>
                            </ol>
                        </div>
                        <div class="col-md-6 text-center">
                            <div id="qr-holder">
                                @if ($instance->qr_code)
                                    <img src="{{ $instance->qr_code }}" alt="WhatsApp QR code" id="qr-image" class="img-fluid rounded-3 border" style="max-width: 280px;">
                                @else
                                    <div class="text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Waiting for QR code&hellip;</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Send a test message --}}
        @if ($isConnected)
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="section-icon" style="background-color: var(--wa-info-light); color: var(--wa-info);"><i class="bi bi-send"></i></span>
                        <div>
                            <h2 class="h5 mb-0">Send a test message</h2>
                            <div class="text-muted small">Try sending a text, photo, video, voice note or document — exactly what the API does.</div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('instances.send-test-message', $instance) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="test-message-to" class="form-label small fw-semibold">To (phone number)</label>
                                <input type="text" class="form-control @error('to') is-invalid @enderror"
                                       id="test-message-to" name="to" placeholder="919876543210" value="{{ old('to') }}" required inputmode="numeric">
                                <div class="form-text">Country code first, digits only: no <code>+</code>, spaces or dashes. E.g. <code>+91 98665 48992</code> &rarr; <code>919866548992</code>.</div>
                                @error('to')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label for="test-message-type" class="form-label small fw-semibold">Message type</label>
                                <select id="test-message-type" name="type" class="form-select">
                                    @foreach (['text' => 'Text', 'image' => 'Image', 'video' => 'Video', 'audio' => 'Audio file', 'voice' => 'Voice note (.ogg)', 'document' => 'Document'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type', 'text') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="test-message-body" class="form-label small fw-semibold" data-message-label>Message</label>
                                <input type="text" class="form-control @error('message') is-invalid @enderror"
                                       id="test-message-body" name="message" placeholder="Type your message…" value="{{ old('message') }}">
                                @error('message')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Media: drop / choose a file (with preview), or paste a link. Hidden for Text. --}}
                            <div class="col-12" data-media-fields @if (old('type', 'text') === 'text') hidden @endif>
                                <div class="row g-3">
                                    <div class="col-lg-7">
                                        <label for="test-message-file" class="media-dropzone d-flex flex-column align-items-center justify-content-center text-center p-4 w-100 h-100 mb-0" data-dropzone>
                                            <i class="bi bi-cloud-arrow-up fs-2 text-primary mb-1"></i>
                                            <span data-dropzone-text>
                                                <strong>Drag &amp; drop a file here</strong> or <span class="text-primary text-decoration-underline">choose one from your computer</span>
                                            </span>
                                            <span class="small text-muted mt-1">Max {{ ini_get('upload_max_filesize') }} per upload</span>
                                            <input type="file" id="test-message-file" name="media" class="d-none">
                                        </label>
                                    </div>
                                    <div class="col-lg-5">
                                        <div class="media-preview h-100 p-3 d-flex flex-column align-items-center justify-content-center text-center" data-preview>
                                            <div class="text-muted small" data-preview-empty>
                                                <i class="bi bi-eye fs-3 d-block mb-1"></i>A preview of your file appears here
                                            </div>
                                            <div data-preview-content hidden></div>
                                            <button type="button" class="btn btn-sm btn-link text-danger mt-2" data-preview-remove hidden>
                                                <i class="bi bi-x-circle me-1"></i>Remove file
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="input-group">
                                            <span class="input-group-text small"><i class="bi bi-link-45deg me-1"></i>or link</span>
                                            <input type="url" class="form-control @error('media_url') is-invalid @enderror"
                                                   id="test-message-media-url" name="media_url" placeholder="https://example.com/photo.jpg" value="{{ old('media_url') }}">
                                            @error('media_url')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-send me-1"></i>Send</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="row g-4 mb-4">
            {{-- API credentials --}}
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <span class="section-icon" style="background-color: var(--wa-purple-light); color: var(--wa-purple);"><i class="bi bi-key"></i></span>
                            <div>
                                <h2 class="h5 mb-0">API credentials</h2>
                                <div class="text-muted small">Use these to authenticate your API requests.</div>
                            </div>
                        </div>

                        <label for="instance-id-value" class="form-label small fw-semibold">Instance ID</label>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control font-monospace small" value="{{ $instance->instance_id }}" id="instance-id-value" readonly>
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

                        <div class="small fw-semibold mb-2">Access tokens</div>
                        @if ($instance->apiTokens->isEmpty())
                            <p class="text-muted small mb-3">No tokens yet.</p>
                        @else
                            <div class="table-responsive border rounded-3 mb-3">
                                <table class="table table-sm align-middle mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Name</th>
                                            <th>Token</th>
                                            <th>Created</th>
                                            <th>Last used</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($instance->apiTokens as $token)
                                            <tr>
                                                <td class="ps-3">{{ $token->name }}</td>
                                                <td class="font-monospace">{{ $token->token_prefix }}&hellip;</td>
                                                <td class="text-nowrap">{{ $token->created_at->format('M j, Y') }}</td>
                                                <td class="text-nowrap">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                                <td class="text-end pe-2">
                                                    @if ($token->revoked_at)
                                                        <span class="badge text-bg-secondary">Revoked</span>
                                                    @else
                                                        <form method="POST" action="{{ route('instances.tokens.destroy', [$instance, $token]) }}"
                                                              onsubmit="return confirm('Revoke this token? Anything using it will stop working immediately.');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0">Revoke</button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($isConnected)
                            <form method="POST" action="{{ route('instances.tokens.store', $instance) }}" class="d-flex gap-2">
                                @csrf
                                <div class="flex-grow-1">
                                    <label for="token-name" class="visually-hidden">New token name</label>
                                    <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                           id="token-name" name="name" placeholder="New token name, e.g. Production server" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus-lg me-1"></i>Generate token</button>
                            </form>
                        @else
                            <p class="text-muted small mb-0">Connect this instance to generate an API token.</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Webhook --}}
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <span class="section-icon" style="background-color: var(--wa-info-light); color: var(--wa-info);"><i class="bi bi-diagram-3"></i></span>
                            <div>
                                <h2 class="h5 mb-0">Webhook</h2>
                                <div class="text-muted small">Get incoming messages forwarded to your server as signed JSON.</div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('instances.webhook.update', $instance) }}" class="mb-3">
                            @csrf
                            <label for="webhook-url" class="form-label small fw-semibold">Webhook URL</label>
                            <div class="input-group">
                                <input type="url" class="form-control font-monospace small @error('webhook_url') is-invalid @enderror"
                                       id="webhook-url" name="webhook_url" placeholder="https://your-app.example.com/webhooks/whatsapp"
                                       value="{{ old('webhook_url', $instance->webhook_url) }}">
                                <button type="submit" class="btn btn-primary">Save</button>
                                @if ($instance->webhook_url)
                                    <button type="submit" name="webhook_url" value="" class="btn btn-outline-danger" title="Clear webhook URL"><i class="bi bi-trash"></i></button>
                                @endif
                                @error('webhook_url')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </form>

                        @if ($instance->webhook_url)
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                @if ($lastDelivery?->status === 'failed')
                                    <span class="badge rounded-pill bg-danger-subtle border border-danger-subtle text-danger-emphasis px-3 py-2">
                                        <i class="bi bi-exclamation-circle me-1"></i>Last delivery failed
                                    </span>
                                @else
                                    <span class="badge rounded-pill bg-wa-light text-primary border px-3 py-2">
                                        <i class="bi bi-check-circle me-1"></i>Webhook is active
                                    </span>
                                @endif
                                @if ($lastDelivery)
                                    <span class="small text-muted">Last event: {{ $lastDelivery->created_at->diffForHumans() }}</span>
                                @endif
                                <form method="POST" action="{{ route('instances.webhook.test', $instance) }}" class="ms-auto">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-send me-1"></i>Send test webhook
                                    </button>
                                </form>
                            </div>
                        @endif

                        @if ($instance->webhook_secret)
                            <label for="webhook-secret" class="form-label small fw-semibold">Signing secret</label>
                            <div class="input-group input-group-sm mb-1">
                                <input type="text" class="form-control font-monospace" value="{{ $instance->webhook_secret }}" id="webhook-secret" readonly>
                                <button class="btn btn-outline-secondary" type="button" data-copy-target="#webhook-secret" title="Copy signing secret">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <p class="small text-muted mb-0">
                                Each request carries an <code>X-Webhook-Signature: sha256=&lt;hmac&gt;</code> header — HMAC-SHA256 of the raw JSON body using this secret.
                            </p>
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
                            <p class="mt-3 mb-1">
                                You also get a <code>message.status</code> event when a message you sent is
                                <code>delivered</code> (✓✓) or <code>read</code> (blue ✓✓):
                            </p>
<pre class="bg-light rounded p-3 mb-0"><code>{
  "event": "message.status",
  "instance_id": "{{ $instance->instance_id }}",
  "message_id": "3EB0A1B2C3D4E5F6",
  "status": "read",
  "to": "919876543210",
  "timestamp": "2026-09-24T10:17:41+00:00"
}</code></pre>
                        </details>

                        {{-- Delivery log --}}
                        <details class="mt-3 small" @if ($lastDelivery?->status === 'failed') open @endif>
                            <summary class="text-primary" style="cursor: pointer;">Recent deliveries ({{ $webhookDeliveries->count() }})</summary>
                            @if ($webhookDeliveries->isEmpty())
                                <p class="text-muted mt-2 mb-0">No webhooks sent yet.</p>
                            @else
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>Event</th>
                                                <th>Status</th>
                                                <th>HTTP</th>
                                                <th>Tries</th>
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
                                                    <td class="text-nowrap">{{ $delivery->created_at->format('M j, H:i:s') }}</td>
                                                    <td><code>{{ $delivery->event }}</code></td>
                                                    <td><span class="badge rounded-pill text-bg-{{ $color }}">{{ $label }}</span></td>
                                                    <td>{{ $delivery->response_status ?? '—' }}</td>
                                                    <td>{{ $delivery->attempts }}</td>
                                                    <td class="text-muted text-truncate" style="max-width: 160px;" title="{{ $delivery->error }}">{{ $delivery->error ?? '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if ($webhookDeliveries->contains(fn ($d) => $d->statusLabel() === 'Queued'))
                                    <p class="text-muted mb-0 mt-2">
                                        <i class="bi bi-info-circle me-1"></i>"Queued" webhooks are waiting to be sent — refresh in a few seconds.
                                    </p>
                                @endif
                            @endif
                        </details>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent messages --}}
        <div class="card shadow-sm" id="recent-messages">
            <div class="card-body p-4 pb-2">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <span class="section-icon bg-wa-light text-primary"><i class="bi bi-chat-left-text"></i></span>
                    <div class="flex-grow-1">
                        <h2 class="h5 mb-0">Recent messages</h2>
                        <div class="text-muted small">Sent and received on this number, newest first.</div>
                    </div>
                    <a href="{{ route('messages.index', ['instance_id' => $instance->instance_id]) }}" class="small text-decoration-none">
                        View all <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

            @if ($messages->isEmpty())
                <div class="card-body pt-0 text-center text-muted py-5">
                    <i class="bi bi-chat-dots fs-2 d-block mb-2"></i>No messages yet.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4" style="width: 60px;"></th>
                                <th>Number</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($messages as $message)
                                @php
                                    $incoming = $message->direction === 'incoming';
                                @endphp
                                <tr>
                                    <td class="ps-4">
                                        <span class="direction-bubble {{ $incoming ? 'in' : 'out' }}" title="{{ $incoming ? 'Received' : 'Sent' }}">
                                            <i class="bi {{ $incoming ? 'bi-arrow-down-left' : 'bi-arrow-up-right' }}"></i>
                                        </span>
                                    </td>
                                    <td class="text-nowrap fw-semibold">{{ $incoming ? $message->from_number : $message->to_number }}</td>
                                    <td>@include('messages._content', ['message' => $message, 'compact' => true])</td>
                                    <td><x-message-status :message="$message" /></td>
                                    <td class="pe-4 text-end text-nowrap small text-muted" title="{{ $message->created_at->format('Y-m-d H:i:s') }}">
                                        {{ $message->created_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($messages->hasPages())
                    {{-- Laravel's links already include "Showing 1 to 10 of N results". --}}
                    <div class="card-body border-top pb-1">
                        {{ $messages->onEachSide(1)->links() }}
                    </div>
                @endif
            @endif
        </div>

        {{-- Connection history --}}
        <div class="card shadow-sm mt-4" id="connection-history">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="section-icon bg-wa-light text-primary"><i class="bi bi-clock-history"></i></span>
                    <div>
                        <h2 class="h5 mb-0">Connection history</h2>
                        <div class="text-muted small">When this number connected and went offline, newest first (last 20).</div>
                    </div>
                </div>

                @if ($connectionEvents->isEmpty())
                    <p class="text-muted small mb-0">Nothing recorded yet.</p>
                @else
                    <ul class="list-unstyled mb-0 small">
                        @foreach ($connectionEvents as $event)
                            <li class="d-flex gap-3 py-2 @if (! $loop->last) border-bottom @endif">
                                <i class="bi bi-{{ $event->icon() }} text-{{ $event->color() }} fs-6"></i>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold">{{ $event->label() }}</div>
                                    @if ($event->detail)
                                        <div class="text-muted text-break">{{ $event->detail }}</div>
                                    @endif
                                </div>
                                <span class="text-muted text-nowrap">{{ $event->created_at->format('M j, H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($isConnected)
<script>
// Test-message form: show the media box for media types; drag & drop /
// choose a file with a live preview (image / video / audio / document),
// pre-selecting the Type from the file; preview a pasted image/video link.
(function () {
    const typeSelect = document.getElementById('test-message-type');
    const mediaFields = document.querySelector('[data-media-fields]');
    const dropzone = document.querySelector('[data-dropzone]');
    const fileInput = document.getElementById('test-message-file');
    const urlInput = document.getElementById('test-message-media-url');

    if (!typeSelect || !mediaFields || !dropzone || !fileInput) {
        return; // the form only exists while the instance is connected
    }

    const dropText = dropzone.querySelector('[data-dropzone-text]');
    const originalText = dropText.innerHTML;
    const previewEmpty = document.querySelector('[data-preview-empty]');
    const previewContent = document.querySelector('[data-preview-content]');
    const removeButton = document.querySelector('[data-preview-remove]');
    const messageLabel = document.querySelector('[data-message-label]');
    let objectUrl = null;

    function syncType() {
        const type = typeSelect.value;
        mediaFields.hidden = type === 'text';
        messageLabel.textContent = type === 'text' ? 'Message' : (type === 'audio' || type === 'voice' ? 'Message (not sent with audio)' : 'Caption (optional)');
    }

    typeSelect.addEventListener('change', syncType);
    syncType();

    function typeForFile(file) {
        if (file.type.startsWith('image/')) return 'image';
        if (file.type.startsWith('video/')) return 'video';
        if (file.type.startsWith('audio/')) return file.type === 'audio/ogg' ? 'voice' : 'audio';
        return 'document';
    }

    // Builds the preview with DOM methods (not innerHTML), so a file name
    // can never inject HTML into the page.
    function showPreview(kind, src, label) {
        previewContent.replaceChildren();

        let element;
        if (kind === 'image') {
            element = document.createElement('img');
            element.alt = 'Preview';
            element.onerror = () => showPreview('document', null, 'Could not load a preview for this link');
        } else if (kind === 'video') {
            element = document.createElement('video');
            element.controls = true;
        } else if (kind === 'audio' || kind === 'voice') {
            element = document.createElement('audio');
            element.controls = true;
            element.style.maxWidth = '100%';
        } else {
            element = document.createElement('i');
            element.className = 'bi bi-file-earmark-text fs-1 text-primary';
        }

        if (src && element.tagName !== 'I') {
            element.src = src;
        }
        previewContent.appendChild(element);

        if (label) {
            const caption = document.createElement('div');
            caption.className = 'small text-muted mt-2 text-break';
            caption.textContent = label;
            previewContent.appendChild(caption);
        }

        previewContent.hidden = false;
        previewEmpty.hidden = true;
    }

    function clearPreview() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        previewContent.replaceChildren();
        previewContent.hidden = true;
        previewEmpty.hidden = false;
        removeButton.hidden = true;
    }

    function showChosenFile() {
        const file = fileInput.files[0];
        clearPreview();

        if (!file) {
            dropText.innerHTML = originalText;
            return;
        }

        const sizeMb = (file.size / 1024 / 1024).toFixed(1);
        const kind = typeForFile(file);

        dropText.textContent = `✓ ${file.name} (${sizeMb} MB) — click to change`;
        typeSelect.value = kind;
        syncType();

        objectUrl = URL.createObjectURL(file);
        showPreview(kind, objectUrl, `${file.name} · ${sizeMb} MB`);
        removeButton.hidden = false;
    }

    fileInput.addEventListener('change', showChosenFile);

    removeButton.addEventListener('click', () => {
        fileInput.value = '';
        showChosenFile();
    });

    ['dragenter', 'dragover'].forEach((name) => dropzone.addEventListener(name, (event) => {
        event.preventDefault();
        dropzone.classList.add('is-dragging');
    }));

    ['dragleave', 'drop'].forEach((name) => dropzone.addEventListener(name, (event) => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
    }));

    dropzone.addEventListener('drop', (event) => {
        if (event.dataTransfer.files.length) {
            fileInput.files = event.dataTransfer.files;
            showChosenFile();
        }
    });

    // Pasted link: preview it too (only when no file is chosen — a file wins).
    urlInput?.addEventListener('change', () => {
        if (fileInput.files.length) return;

        const url = urlInput.value.trim();
        if (!/^https?:\/\//i.test(url)) {
            clearPreview();
            return;
        }

        const type = typeSelect.value;
        showPreview(['image', 'video', 'audio', 'voice'].includes(type) ? type : 'document', url, 'From link');
    });
})();
</script>
@endif

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
