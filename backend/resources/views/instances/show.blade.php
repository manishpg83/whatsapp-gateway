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

    // Header status pill (same look as the instances list).
    [$pillLabel, $pillTone] = match (true) {
        $isConnected => ['Connected', 'green'],
        $isResuming => ['Reconnecting', 'amber'],
        $instance->status === 'qr_pending' => ['Waiting for QR scan', 'amber'],
        $isWaiting => ['Connecting', 'amber'],
        $instance->status === 'logged_out' => ['Logged out', 'red'],
        default => ['Disconnected', 'grey'],
    };

    // In-page section links (only the sections this page actually shows).
    $sections = array_filter([
        'instance-status' => ['bi-activity', 'Status'],
        'send-test' => $isConnected ? ['bi-send', 'Test message'] : null,
        'credentials' => ['bi-key', 'API credentials'],
        'webhook' => ['bi-diagram-3', 'Webhook'],
        'recent-messages' => ['bi-chat-left-text', 'Messages'],
        'connection-history' => ['bi-clock-history', 'History'],
    ]);
@endphp

<div class="row justify-content-center">
    <div class="col-xl-11">

        {{-- Page header --}}
        <nav aria-label="breadcrumb" class="db-in">
            <ol class="breadcrumb ish-crumbs mb-2">
                <li class="breadcrumb-item"><a href="{{ route('instances.index') }}"><i class="bi bi-hdd-stack me-1"></i>Instances</a></li>
                <li class="breadcrumb-item active text-truncate" aria-current="page">{{ $instance->name }}</li>
            </ol>
        </nav>
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3 db-in" style="--i: 1;">
            <div class="d-flex align-items-center gap-3" style="min-width: 0;">
                <span class="ish-avatar in-tone-{{ $pillTone }}"><i class="bi bi-whatsapp"></i></span>
                <div style="min-width: 0;">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h1 class="h3 mb-0 text-break">{{ $instance->name }}</h1>
                        <span class="in-pill in-pill-{{ $pillTone }}"><span class="in-pill-dot"></span>{{ $pillLabel }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 text-muted small" style="min-width: 0;">
                        <span class="font-monospace text-truncate">{{ $instance->instance_id }}</span>
                        <input type="hidden" id="header-instance-id" value="{{ $instance->instance_id }}">
                        <button type="button" class="btn btn-sm btn-outline-secondary ish-copy" data-copy-target="#header-instance-id" title="Copy instance ID" aria-label="Copy instance ID">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                <a href="{{ route('api-logs.index', ['instance_id' => $instance->instance_id]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-clock-history me-1"></i>API Logs
                </a>
                <a href="{{ route('instances.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to instances
                </a>
            </div>
        </div>

        {{-- Jump-to-section bar (sticks under the top bar while scrolling). --}}
        <nav class="ish-jump db-in mb-4" style="--i: 2;" aria-label="Sections on this page" data-ish-jump>
            @foreach ($sections as $anchor => [$icon, $label])
                <a href="#{{ $anchor }}" class="ish-jump-link {{ $loop->first ? 'active' : '' }}" data-ish-target="{{ $anchor }}">
                    <i class="bi {{ $icon }}"></i>{{ $label }}
                </a>
            @endforeach
        </nav>

        {{-- Status banner --}}
        <div id="instance-status" data-instance-status="{{ $instance->status }}" style="--i: 3;"
             class="card shadow-sm mb-4 overflow-hidden ish-section db-in {{ $isConnected ? 'instance-hero' : '' }}">
            <div class="card-body p-4 p-md-5">
                @if ($isConnected)
                    <div class="d-flex flex-column flex-md-row align-items-md-center gap-4">
                        <div class="instance-hero-icon ish-hero-icon ish-ping"><span class="inner"><i class="bi bi-check-lg"></i></span></div>
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
                                <span class="stat-chip"><i class="bi bi-arrow-up-right text-primary me-1"></i><span data-count-up="{{ $sentCount }}" data-count-suffix=" sent">{{ $sentCount }} sent</span></span>
                                <span class="stat-chip"><i class="bi bi-arrow-down-left text-info me-1"></i><span data-count-up="{{ $receivedCount }}" data-count-suffix=" received">{{ $receivedCount }} received</span></span>
                                @if ($failedCount > 0)
                                    <span class="stat-chip text-danger"><i class="bi bi-exclamation-triangle me-1"></i><span data-count-up="{{ $failedCount }}" data-count-suffix=" failed">{{ $failedCount }} failed</span></span>
                                @endif
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-self-md-start">
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
                        <div class="d-flex flex-wrap gap-2">
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
                        <div class="instance-hero-icon ish-hero-icon ish-orbit"><span class="inner"><i class="bi bi-arrow-repeat ish-spin"></i></span></div>
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
                    <div class="row align-items-center g-4 g-lg-5">
                        <div class="col-md-6 order-2 order-md-1">
                            <div class="fs-4 fw-semibold mb-1">Link your WhatsApp</div>
                            <p class="text-muted small mb-4">Keep this page open: it updates by itself once your phone is linked.</p>
                            <ol class="ish-qr-steps">
                                <li style="--i: 0;">
                                    <span class="ish-qr-step-num">1</span>
                                    <span><i class="bi bi-phone me-1 text-primary"></i>Open <strong>WhatsApp</strong> on your phone</span>
                                </li>
                                <li style="--i: 1;">
                                    <span class="ish-qr-step-num">2</span>
                                    <span><i class="bi bi-gear me-1 text-primary"></i>Go to <strong>Settings &rarr; Linked Devices</strong></span>
                                </li>
                                <li style="--i: 2;">
                                    <span class="ish-qr-step-num">3</span>
                                    <span><i class="bi bi-qr-code-scan me-1 text-primary"></i>Tap <strong>Link a Device</strong> and scan this code</span>
                                </li>
                            </ol>
                            <div class="ish-qr-note">
                                <i class="bi bi-arrow-repeat"></i>
                                The code refreshes automatically until it's scanned.
                            </div>
                        </div>
                        <div class="col-md-6 order-1 order-md-2">
                            <div class="ish-qr-frame mx-auto">
                                <span class="ish-qr-corner tl"></span><span class="ish-qr-corner tr"></span>
                                <span class="ish-qr-corner bl"></span><span class="ish-qr-corner br"></span>
                                <div id="qr-holder" class="ish-qr-holder">
                                    @if ($instance->qr_code)
                                        <img src="{{ $instance->qr_code }}" alt="WhatsApp QR code" id="qr-image" class="img-fluid rounded-3 border" style="max-width: 280px;">
                                    @else
                                        <div class="text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Waiting for QR code&hellip;</div>
                                    @endif
                                </div>
                                <span class="ish-qr-scan" aria-hidden="true"></span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Send a test message --}}
        @if ($isConnected)
            <div class="card shadow-sm mb-4 ish-section db-in" id="send-test" style="--i: 4;">
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

                    {{-- Is this number on WhatsApp? (same as POST /api/v1/numbers/check) --}}
                    <div class="border-top mt-4 pt-4" id="check-number">
                        <div class="small fw-semibold mb-2"><i class="bi bi-person-check me-1 text-primary"></i>Check if a number is on WhatsApp</div>
                        <form method="POST" action="{{ route('instances.check-number', $instance) }}" class="d-flex flex-column flex-sm-row gap-2">
                            @csrf
                            <div class="flex-grow-1">
                                <label for="check-number-input" class="visually-hidden">Phone number to check</label>
                                <input type="text" class="form-control @error('check_number') is-invalid @enderror"
                                       id="check-number-input" name="check_number" placeholder="919876543210"
                                       value="{{ old('check_number') }}" required inputmode="numeric">
                                @error('check_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-outline-primary text-nowrap"><i class="bi bi-search me-1"></i>Check</button>
                        </form>

                        @if ($check = session('number_check'))
                            @if ($check['exists'])
                                <div class="alert alert-success small mt-2 mb-0 py-2">
                                    <i class="bi bi-check-circle me-1"></i><strong>{{ $check['number'] }}</strong> is on WhatsApp.
                                    @if ($check['whatsapp_number'] && $check['whatsapp_number'] !== $check['number'])
                                        WhatsApp knows it as <strong>{{ $check['whatsapp_number'] }}</strong>.
                                    @endif
                                </div>
                            @else
                                <div class="alert alert-warning small mt-2 mb-0 py-2">
                                    <i class="bi bi-x-circle me-1"></i><strong>{{ $check['number'] }}</strong> is not on WhatsApp. Check the country code and digits.
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-4 mb-4">
            {{-- API credentials --}}
            <div class="col-lg-6">
                <div class="card shadow-sm h-100 ish-section db-in" id="credentials" style="--i: 5;">
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
                            <form method="POST" action="{{ route('instances.tokens.store', $instance) }}" class="d-flex flex-column flex-sm-row gap-2">
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
                <div class="card shadow-sm h-100 ish-section db-in" id="webhook" style="--i: 6;">
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
        <div class="card shadow-sm ish-section db-in" id="recent-messages" style="--i: 7;">
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
        <div class="card shadow-sm mt-4 ish-section db-in" id="connection-history" style="--i: 8;">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 mb-3">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <span class="section-icon bg-wa-light text-primary flex-shrink-0"><i class="bi bi-clock-history"></i></span>
                        <div>
                            <h2 class="h5 mb-0">Connection history</h2>
                            <div class="text-muted small">
                                @if ($historyDate)
                                    Showing {{ $historyDate->format('D, M j, Y') }} only.
                                @else
                                    When this number connected and went offline, by day (latest 50 events).
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Date filter: picking a day reloads the page showing only that day. --}}
                    <form method="GET" action="{{ route('instances.show', $instance) }}#connection-history" class="d-flex gap-2">
                        <label for="history-date" class="visually-hidden">Show history for date</label>
                        <input type="date" id="history-date" name="history_date" class="form-control form-control-sm"
                               value="{{ $historyDate?->toDateString() }}"
                               max="{{ now()->toDateString() }}"
                               @if ($historyFirstDate) min="{{ \Illuminate\Support\Carbon::parse($historyFirstDate)->toDateString() }}" @endif
                               onchange="if (this.value) this.form.submit()">
                        <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-funnel me-1"></i>Show</button>
                        @if ($historyDate)
                            <a href="{{ route('instances.show', $instance) }}#connection-history" class="btn btn-sm btn-outline-secondary text-nowrap">
                                <i class="bi bi-x-lg me-1"></i>Clear
                            </a>
                        @endif
                    </form>
                </div>

                @if ($connectionEventsByDay->isEmpty())
                    <p class="text-muted small mb-0">
                        {{ $historyDate ? 'Nothing happened on this day.' : 'Nothing recorded yet.' }}
                    </p>
                @else
                    @foreach ($connectionEventsByDay as $day => $dayEvents)
                        @php
                            $dayDate = \Illuminate\Support\Carbon::parse($day);
                            $dayLabel = match (true) {
                                $dayDate->isToday() => 'Today',
                                $dayDate->isYesterday() => 'Yesterday',
                                default => $dayDate->format('D, M j, Y'),
                            };
                        @endphp
                        <div class="@if (! $loop->first) mt-3 @endif">
                            <div class="d-flex align-items-center gap-2 bg-light rounded-2 px-3 py-2 small">
                                <i class="bi bi-calendar3 text-muted"></i>
                                <span class="fw-semibold">{{ $dayLabel }}</span>
                                <span class="text-muted ms-auto">{{ $dayEvents->count() }} {{ Str::plural('event', $dayEvents->count()) }}</span>
                            </div>
                            <ul class="list-unstyled mb-0 small px-md-2">
                                @foreach ($dayEvents as $event)
                                    <li class="d-flex gap-3 py-2 @if (! $loop->last) border-bottom @endif">
                                        <i class="bi bi-{{ $event->icon() }} text-{{ $event->color() }} fs-6 flex-shrink-0"></i>
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <div class="fw-semibold">{{ $event->label() }}</div>
                                            @if ($event->detail)
                                                <div class="text-muted text-break">{{ $event->detail }}</div>
                                            @endif
                                        </div>
                                        <span class="text-muted text-nowrap flex-shrink-0">{{ $event->created_at->format('H:i') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
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

// Jump bar: highlight the section currently in view, and keep the active
// link scrolled into view inside the bar on narrow screens.
(function () {
    const bar = document.querySelector('[data-ish-jump]');
    if (!bar || !('IntersectionObserver' in window)) {
        return;
    }

    const links = [...bar.querySelectorAll('[data-ish-target]')];
    const setActive = (id) => links.forEach((link) => {
        const on = link.dataset.ishTarget === id;
        link.classList.toggle('active', on);
        if (on) {
            bar.scrollTo({ left: link.offsetLeft - 16, behavior: 'smooth' });
        }
    });

    const observer = new IntersectionObserver((entries) => {
        entries.filter((entry) => entry.isIntersecting)
            .forEach((entry) => setActive(entry.target.id));
    }, { rootMargin: '-35% 0px -60% 0px' });

    links.forEach((link) => {
        const section = document.getElementById(link.dataset.ishTarget);
        if (section) {
            observer.observe(section);
        }
    });
})();
</script>
@endsection
