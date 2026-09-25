@extends('layouts.app')

@section('title', 'API Docs')

@section('content')
@php
    $toc = [
        'getting-started' => ['bi-rocket-takeoff', 'Getting started'],
        'authentication' => ['bi-shield-lock', 'Authentication'],
        'responsible-use' => ['bi-shield-exclamation', 'Protect your number'],
        'endpoints' => ['bi-list-ul', 'Endpoints'],
        'endpoint-send' => ['bi-send', 'Send a message'],
        'endpoint-status' => ['bi-check2-all', 'Message status'],
        'endpoint-check-numbers' => ['bi-person-check', 'Check numbers'],
        'code-samples' => ['bi-terminal', 'Code samples'],
    ];
@endphp

<div class="dc-layout">
    {{-- Table of contents: a sticky sidebar on large screens, a swipeable chip bar below that. --}}
    <nav class="dc-toc db-in" aria-label="On this page" data-dc-toc>
        <div class="dc-toc-title">On this page</div>
        @foreach ($toc as $anchor => [$icon, $label])
            <a href="#{{ $anchor }}" class="dc-toc-link {{ $loop->first ? 'active' : '' }} {{ str_starts_with($anchor, 'endpoint-') ? 'dc-toc-sub' : '' }}" data-dc-target="{{ $anchor }}">
                <i class="bi {{ $icon }}"></i><span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>

    <div class="dc-main">
        {{-- Hero --}}
        <header class="dc-hero db-in" style="--i: 1;">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="dc-version">v1</span>
                <span class="dc-hero-tag"><i class="bi bi-braces"></i> REST &middot; JSON</span>
            </div>
            <h1 class="dc-hero-title">API Docs</h1>
            <p class="dc-hero-sub">How to connect an instance, send WhatsApp messages and check their status through the API.</p>

            <div class="dc-hero-facts">
                <div class="dc-fact dc-fact-wide">
                    <span class="dc-fact-label">Base URL</span>
                    <span class="dc-fact-value">
                        <code id="dc-base-url">{{ url('/api/v1') }}</code>
                        <button type="button" class="dc-copy-sm" data-dc-copy-text="{{ url('/api/v1') }}" title="Copy base URL"><i class="bi bi-clipboard"></i></button>
                    </span>
                </div>
                <div class="dc-fact">
                    <span class="dc-fact-label">Auth</span>
                    <span class="dc-fact-value">Bearer token</span>
                </div>
                <div class="dc-fact">
                    <span class="dc-fact-label">Endpoints</span>
                    <span class="dc-fact-value">3</span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <a href="{{ route('instances.index') }}" class="btn btn-light fw-semibold dc-btn-lift"><i class="bi bi-key me-1"></i>Get your token</a>
                <a href="#endpoint-send" class="btn btn-outline-light fw-semibold dc-btn-lift">Send your first message <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
        </header>

        {{-- Getting started --}}
        <section class="dc-section db-in" id="getting-started" style="--i: 2;">
            <h2 class="dc-h2"><span class="dc-h2-icon"><i class="bi bi-rocket-takeoff"></i></span>Getting started</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="dc-step">
                        <span class="dc-step-num">1</span>
                        <p class="mb-0">
                            <a href="{{ route('instances.create') }}">Create an instance</a> and scan its QR code with
                            WhatsApp on your phone until its status shows <span class="badge text-bg-success">Connected</span>.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dc-step">
                        <span class="dc-step-num">2</span>
                        <p class="mb-0">
                            Open the connected instance's page and generate an <strong>API credential</strong>. You'll be
                            shown an <code>instance_id</code> and an <code>access_token</code> — the token is shown
                            <strong>once</strong>, so copy it somewhere safe immediately.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dc-step">
                        <span class="dc-step-num">3</span>
                        <p class="mb-0">Call the API below with that <code>instance_id</code> and <code>access_token</code>.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Authentication --}}
        <section class="dc-section db-in" id="authentication" style="--i: 3;">
            <h2 class="dc-h2"><span class="dc-h2-icon dc-tone-blue"><i class="bi bi-shield-lock"></i></span>Authentication</h2>
            <p>Send your access token as a Bearer token in the <code>Authorization</code> header:</p>
            <div class="dc-code">
                <div class="dc-code-head"><span>HTTP header</span><button type="button" class="dc-copy" data-dc-copy><i class="bi bi-clipboard"></i> Copy</button></div>
<pre><code>Authorization: Bearer YOUR_ACCESS_TOKEN</code></pre>
            </div>
            <ul class="dc-bullets mt-3 mb-0">
                <li>Tokens are tied to one instance. The <code>instance_id</code> in the request body must match the token.</li>
                <li>A revoked or unknown token gets a <code>401</code>.</li>
                <li>Never share your access token or commit it to a public repository.</li>
            </ul>
        </section>

        {{-- Responsible use — shown before the endpoints on purpose. --}}
        <section class="dc-section dc-warn db-in" id="responsible-use" style="--i: 4;">
            <h2 class="dc-h2"><span class="dc-h2-icon dc-tone-amber"><i class="bi bi-shield-exclamation"></i></span>Protect your WhatsApp number</h2>
            <p class="text-muted">WhatsApp can restrict or ban a number that looks like it's spamming — and we can't undo that.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="dc-dodont dc-do">
                        <div class="dc-dodont-title"><i class="bi bi-check-circle-fill"></i>Do</div>
                        <ul class="mb-0">
                            <li>Only message people who <strong>gave you their number</strong> and expect to hear from you.</li>
                            <li>Send messages people actually want: order updates, OTPs, appointment reminders, replies.</li>
                            <li>Keep sending volumes <strong>steady</strong> — build up gradually on a new number.</li>
                            <li>Personalise messages (name, order number) instead of sending one identical text to everyone.</li>
                            <li>Stop immediately when someone asks you to, and make it easy to ask.</li>
                            <li>Test with a number you can afford to lose.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="dc-dodont dc-dont">
                        <div class="dc-dodont-title"><i class="bi bi-x-circle-fill"></i>Don't</div>
                        <ul class="mb-0">
                            <li>Send bulk promotional or marketing blasts to bought or scraped lists.</li>
                            <li>Send hundreds of messages in a sudden burst from a fresh number.</li>
                            <li>Message people who never contacted you or opted in.</li>
                            <li>Send the exact same message and link to large numbers of people.</li>
                            <li>Keep messaging numbers that don't reply or have blocked you.</li>
                        </ul>
                    </div>
                </div>
            </div>
            <p class="small text-muted mb-0 mt-3">
                You are responsible for everything sent from your number — see
                <a href="{{ route('terms') }}#bulk-messaging">section 4 of our Terms</a>.
                This service does not offer, and will not build, anything designed to get around WhatsApp's limits.
            </p>
        </section>

        {{-- Endpoint overview --}}
        <section class="dc-section db-in" id="endpoints" style="--i: 5;">
            <h2 class="dc-h2"><span class="dc-h2-icon dc-tone-purple"><i class="bi bi-code-slash"></i></span>Endpoints</h2>
            <div class="dc-endpoint-list">
                @foreach ([
                    ['POST', '/api/v1/messages/send', '#endpoint-send', 'Send a text, image, video, audio, voice note or document', '30 / min'],
                    ['GET', '/api/v1/messages/{message_id}', '#endpoint-status', "Check a sent message's status", '60 / min'],
                    ['POST', '/api/v1/numbers/check', '#endpoint-check-numbers', 'Check if numbers are on WhatsApp (up to 20 per request)', '10 / min'],
                ] as [$method, $path, $anchor, $what, $limit])
                    <a href="{{ $anchor }}" class="dc-endpoint-row">
                        <span class="dc-method dc-{{ strtolower($method) }}">{{ $method }}</span>
                        <span class="dc-endpoint-path"><code>{{ $path }}</code><span>{{ $what }}</span></span>
                        <span class="dc-limit"><i class="bi bi-speedometer2"></i>{{ $limit }}</span>
                        <i class="bi bi-chevron-right dc-endpoint-go" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>
            <p class="text-muted small mb-0 mt-3">Rate limits are per access token and counted separately for each endpoint.</p>
        </section>

        {{-- 1. Send a message --}}
        <section class="dc-section dc-endpoint db-in" id="endpoint-send" style="--i: 6;">
            <div class="dc-endpoint-head">
                <span class="dc-method dc-post">POST</span>
                <code class="dc-endpoint-url">/api/v1/messages/send</code>
                <button type="button" class="dc-copy-sm ms-auto" data-dc-copy-text="{{ url('/api/v1/messages/send') }}" title="Copy URL"><i class="bi bi-clipboard"></i></button>
            </div>
            <h2 class="dc-h2 mt-3">Send a message (text or media)</h2>

            <h3 class="dc-h3">Request body</h3>
            <table class="dc-table dc-stack">
                <thead>
                    <tr><th>Field</th><th>Type</th><th>Description</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>instance_id</code></td>
                        <td>string (UUID)</td>
                        <td>Your instance's ID. Must match the token used.</td>
                    </tr>
                    <tr>
                        <td><code>to</code></td>
                        <td>string</td>
                        <td>
                            Recipient's phone number, digits only with country code (e.g. <code>919866548992</code>), 7-15 digits.
                            <div class="small text-muted mt-1">
                                <i class="bi bi-info-circle me-1"></i>Country code first, then the number: no <code>+</code>, no spaces, no dashes, no leading <code>0</code>.
                                For example India <code>+91 98665 48992</code> &rarr; <code>919866548992</code>, USA <code>+1 (249) 989-3170</code> &rarr; <code>12499893170</code>.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><code>type</code> <span class="dc-opt">optional</span></td>
                        <td>string</td>
                        <td>
                            <code>text</code> (default), <code>image</code>, <code>video</code>, <code>audio</code>,
                            <code>voice</code> or <code>document</code>. Leave it out to send plain text.
                        </td>
                    </tr>
                    <tr>
                        <td><code>message</code></td>
                        <td>string</td>
                        <td>
                            The text, up to 4096 characters. <strong>Required for text.</strong>
                            For <code>image</code>, <code>video</code> and <code>document</code> it's an optional caption;
                            it's ignored for <code>audio</code> and <code>voice</code>.
                        </td>
                    </tr>
                    <tr>
                        <td><code>media_url</code> <span class="dc-opt">media only</span></td>
                        <td>string (URL)</td>
                        <td>
                            A public <code>http(s)</code> link to the file. We download it, check it, and send it.
                            <strong>Every type except text needs either <code>media_url</code> or <code>media</code></strong> (not both).
                        </td>
                    </tr>
                    <tr>
                        <td><code>media</code> <span class="dc-opt">media only</span></td>
                        <td>file</td>
                        <td>
                            Upload the file directly instead of giving a link — send the request as
                            <code>multipart/form-data</code> (see the example below). Max upload on this server:
                            <strong>{{ ini_get('upload_max_filesize') }}</strong>.
                        </td>
                    </tr>
                    <tr>
                        <td><code>file_name</code> <span class="dc-opt">optional</span></td>
                        <td>string</td>
                        <td>For <code>document</code>: the name the recipient sees, e.g. <code>Invoice-42.pdf</code>. Defaults to the name in the URL.</td>
                    </tr>
                </tbody>
            </table>

            <h3 class="dc-h3">Media rules</h3>
            <table class="dc-table dc-stack">
                <thead>
                    <tr><th><code>type</code></th><th>Accepted files</th><th>Max size</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>image</code></td><td>JPG, PNG, WebP</td><td>5 MB</td></tr>
                    <tr><td><code>video</code></td><td>MP4, 3GP</td><td>16 MB</td></tr>
                    <tr><td><code>audio</code></td><td>MP3, OGG, M4A, AAC, AMR — sent as a music/audio file</td><td>16 MB</td></tr>
                    <tr><td><code>voice</code></td><td>OGG (Opus) only — shows as a playable voice note</td><td>16 MB</td></tr>
                    <tr><td><code>document</code></td><td>Any file (PDF, Word, Excel, ZIP, …)</td><td>100 MB</td></tr>
                </tbody>
            </table>
            <p class="small text-muted">
                The file type is checked from the file's actual content, not its name. The link must be publicly reachable
                (no login) and respond within 30 seconds.
            </p>

            <h3 class="dc-h3">Responses</h3>
            <table class="dc-table dc-stack">
                <thead>
                    <tr><th>Status</th><th>Body</th><th>Meaning</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="dc-http dc-http-ok">200</span></td>
                        <td><code>{"success": true, "message_id": "..."}</code></td>
                        <td>Message sent.</td>
                    </tr>
                    <tr>
                        <td><span class="dc-http dc-http-bad">401</span></td>
                        <td>&mdash;</td>
                        <td>Missing, unknown, or revoked access token.</td>
                    </tr>
                    <tr>
                        <td><span class="dc-http dc-http-warn">422</span></td>
                        <td><code>{"success": false, "error": "instance_id does not match this token"}</code></td>
                        <td>The body's <code>instance_id</code> doesn't belong to this token.</td>
                    </tr>
                    <tr>
                        <td><span class="dc-http dc-http-warn">422</span></td>
                        <td><code>{"success": false, "error": "Instance is not connected"}</code></td>
                        <td>The instance isn't currently connected to WhatsApp.</td>
                    </tr>
                    <tr>
                        <td><span class="dc-http dc-http-warn">422</span></td>
                        <td><code>{"success": false, "error": "You've reached your plan's monthly message limit. Upgrade to send more."}</code></td>
                        <td>Your <a href="{{ route('billing.index') }}">plan's</a> monthly message quota is used up.</td>
                    </tr>
                    <tr>
                        <td><span class="dc-http dc-http-warn">422</span></td>
                        <td><code>{"success": false, "error": "The file at media_url is too large (max 5 MB for this type)."}</code></td>
                        <td>
                            <code>media_url</code> couldn't be used: not reachable, not public, too large, empty, or the wrong
                            file type for <code>type</code>. The <code>error</code> says which.
                        </td>
                    </tr>
                    <tr>
                        <td><span class="dc-http dc-http-err">502</span></td>
                        <td><code>{"success": false, "error": "Could not send message"}</code></td>
                        <td>The message could not be delivered (e.g. invalid number).</td>
                    </tr>
                </tbody>
            </table>

            <p class="dc-limit-note">
                <i class="bi bi-speedometer2"></i>Rate limit: 30 requests per minute per access token.
                Code samples in 6 languages are <a href="#code-samples">below</a>.
            </p>

            <h3 class="dc-h3">Example: send a PDF with a caption</h3>
            <div class="dc-code">
                <div class="dc-code-head"><span>curl &middot; JSON</span><button type="button" class="dc-copy" data-dc-copy><i class="bi bi-clipboard"></i> Copy</button></div>
<pre><code>curl -X POST {{ url('/api/v1/messages/send') }} \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "instance_id": "YOUR_INSTANCE_ID",
    "to": "919876543210",
    "type": "document",
    "media_url": "https://example.com/files/invoice-42.pdf",
    "file_name": "Invoice-42.pdf",
    "message": "Here is your invoice"
  }'</code></pre>
            </div>

            <h3 class="dc-h3">Example: upload a photo from your computer</h3>
            <div class="dc-code">
                <div class="dc-code-head"><span>curl &middot; multipart/form-data</span><button type="button" class="dc-copy" data-dc-copy><i class="bi bi-clipboard"></i> Copy</button></div>
<pre><code>curl -X POST {{ url('/api/v1/messages/send') }} \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Accept: application/json" \
  -F "instance_id=YOUR_INSTANCE_ID" \
  -F "to=919876543210" \
  -F "type=image" \
  -F "message=Here is the photo" \
  -F "media=@/path/to/photo.jpg"</code></pre>
            </div>
        </section>

        {{-- 2. Check message status --}}
        <section class="dc-section dc-endpoint db-in" id="endpoint-status" style="--i: 7;">
            <div class="dc-endpoint-head">
                <span class="dc-method dc-get">GET</span>
                <code class="dc-endpoint-url">/api/v1/messages/{message_id}</code>
                <button type="button" class="dc-copy-sm ms-auto" data-dc-copy-text="{{ url('/api/v1/messages/') }}/" title="Copy URL"><i class="bi bi-clipboard"></i></button>
            </div>
            <h2 class="dc-h2 mt-3">Check message status</h2>

            <h3 class="dc-h3">Request</h3>
            <p>
                No body. Put the <code>message_id</code> returned by <code>POST /api/v1/messages/send</code> in the URL
                and use the same access token. A token can only look up messages sent from its own instance.
            </p>

            <h3 class="dc-h3">Responses</h3>
            <table class="dc-table dc-stack">
                <thead>
                    <tr><th>Status</th><th>Body</th><th>Meaning</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="dc-http dc-http-ok">200</span></td>
                        <td><code>{"success": true, "message": {...}}</code></td>
                        <td>Message found (full example below).</td>
                    </tr>
                    <tr>
                        <td><span class="dc-http dc-http-bad">401</span></td>
                        <td>&mdash;</td>
                        <td>Missing, unknown, or revoked access token.</td>
                    </tr>
                    <tr>
                        <td><span class="dc-http dc-http-warn">404</span></td>
                        <td><code>{"success": false, "error": "Message not found"}</code></td>
                        <td>No message with this ID was sent from this token's instance.</td>
                    </tr>
                </tbody>
            </table>

            <div class="row g-3">
                <div class="col-xl-6">
                    <h3 class="dc-h3 mt-0">Example request</h3>
                    <div class="dc-code">
                        <div class="dc-code-head"><span>curl</span><button type="button" class="dc-copy" data-dc-copy><i class="bi bi-clipboard"></i> Copy</button></div>
<pre><code>curl {{ url('/api/v1/messages/MESSAGE_ID') }} \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"</code></pre>
                    </div>

                    <h3 class="dc-h3"><code>status</code> values</h3>
                    <ul class="dc-status-list">
                        <li><span class="dc-sv">pending</span>being sent right now.</li>
                        <li><span class="dc-sv">sent</span>handed over to WhatsApp successfully (✓).</li>
                        <li><span class="dc-sv">delivered</span>reached the recipient's phone (✓✓). <code>delivered_at</code> is set.</li>
                        <li><span class="dc-sv">read</span>the recipient opened it (blue ✓✓). <code>read_at</code> is set.</li>
                        <li><span class="dc-sv">failed</span>could not be sent.</li>
                    </ul>
                    <p class="small text-muted mb-0">
                        <code>read</code> only appears if the recipient has read receipts turned on in WhatsApp.
                        Want updates pushed to you instead of polling? Set a webhook on your instance — you'll get a
                        <code>message.status</code> event each time a message is delivered or read.
                    </p>
                </div>
                <div class="col-xl-6">
                    <h3 class="dc-h3 mt-0">Example response</h3>
                    <div class="dc-code">
                        <div class="dc-code-head"><span>200 &middot; JSON</span><button type="button" class="dc-copy" data-dc-copy><i class="bi bi-clipboard"></i> Copy</button></div>
<pre><code>{
  "success": true,
  "message": {
    "message_id": "3EB0A1B2C3D4E5F6",
    "instance_id": "YOUR_INSTANCE_ID",
    "direction": "outgoing",
    "type": "text",
    "to": "919876543210",
    "status": "read",
    "delivered_at": "2026-09-24T10:15:05+00:00",
    "read_at": "2026-09-24T10:17:41+00:00",
    "created_at": "2026-09-24T10:15:03+00:00",
    "updated_at": "2026-09-24T10:15:04+00:00"
  }
}</code></pre>
                    </div>
                </div>
            </div>

            <p class="dc-limit-note"><i class="bi bi-speedometer2"></i>Rate limit: 60 requests per minute per access token.</p>
        </section>

        {{-- 3. Check numbers --}}
        <section class="dc-section dc-endpoint db-in" id="endpoint-check-numbers" style="--i: 8;">
            <div class="dc-endpoint-head">
                <span class="dc-method dc-post">POST</span>
                <code class="dc-endpoint-url">/api/v1/numbers/check</code>
                <button type="button" class="dc-copy-sm ms-auto" data-dc-copy-text="{{ url('/api/v1/numbers/check') }}" title="Copy URL"><i class="bi bi-clipboard"></i></button>
            </div>
            <h2 class="dc-h2 mt-3">Check if numbers are on WhatsApp</h2>
            <p class="text-muted">
                Check before you send, so you don't waste a message on a number without WhatsApp.
                Nothing is sent, and it doesn't count toward your monthly message limit. The instance must be connected.
            </p>

            <h3 class="dc-h3">Request body (JSON)</h3>
            <table class="dc-table dc-stack">
                <thead>
                    <tr><th>Field</th><th>Type</th><th>Description</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>instance_id</code></td>
                        <td>string (UUID)</td>
                        <td>Your instance's ID. Must match the token used.</td>
                    </tr>
                    <tr>
                        <td><code>numbers</code></td>
                        <td>array of strings</td>
                        <td>
                            1 to 20 phone numbers, same format as <code>to</code> when sending:
                            country code first, digits only (e.g. <code>919876543210</code>).
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3 class="dc-h3">Responses</h3>
            <table class="dc-table dc-stack">
                <thead>
                    <tr><th>Status</th><th>Body</th><th>Meaning</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="dc-http dc-http-ok">200</span></td>
                        <td><code>{"success": true, "results": [...]}</code></td>
                        <td>One result per number (example below).</td>
                    </tr>
                    <tr>
                        <td><span class="dc-http dc-http-bad">401</span></td>
                        <td>&mdash;</td>
                        <td>Missing, unknown, or revoked access token.</td>
                    </tr>
                    <tr>
                        <td><span class="dc-http dc-http-warn">422</span></td>
                        <td><code>{"success": false, "error": "..."}</code></td>
                        <td>Invalid numbers, more than 20, wrong <code>instance_id</code>, or instance not connected.</td>
                    </tr>
                    <tr>
                        <td><span class="dc-http dc-http-warn">429</span></td>
                        <td>&mdash;</td>
                        <td>Too many requests; wait a minute.</td>
                    </tr>
                    <tr>
                        <td><span class="dc-http dc-http-err">502</span></td>
                        <td><code>{"success": false, "error": "Could not check numbers right now"}</code></td>
                        <td>WhatsApp couldn't be asked right now; try again shortly.</td>
                    </tr>
                </tbody>
            </table>

            <div class="row g-3">
                <div class="col-xl-6">
                    <h3 class="dc-h3 mt-0">Example request</h3>
                    <div class="dc-code">
                        <div class="dc-code-head"><span>curl &middot; JSON</span><button type="button" class="dc-copy" data-dc-copy><i class="bi bi-clipboard"></i> Copy</button></div>
<pre><code>curl -X POST {{ url('/api/v1/numbers/check') }} \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "instance_id": "YOUR_INSTANCE_ID",
    "numbers": ["919876543210", "12499793168"]
  }'</code></pre>
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        <code>whatsapp_number</code> is the number as WhatsApp knows it. It's usually the same as
                        <code>number</code>, but WhatsApp adjusts some countries' numbers. It's <code>null</code> when the
                        number isn't on WhatsApp.
                    </p>
                </div>
                <div class="col-xl-6">
                    <h3 class="dc-h3 mt-0">Example response</h3>
                    <div class="dc-code">
                        <div class="dc-code-head"><span>200 &middot; JSON</span><button type="button" class="dc-copy" data-dc-copy><i class="bi bi-clipboard"></i> Copy</button></div>
<pre><code>{
  "success": true,
  "results": [
    { "number": "919876543210", "on_whatsapp": true,  "whatsapp_number": "919876543210" },
    { "number": "12499793168",  "on_whatsapp": false, "whatsapp_number": null }
  ]
}</code></pre>
                    </div>
                </div>
            </div>

            <p class="dc-limit-note">
                <i class="bi bi-speedometer2"></i>Rate limit: 10 requests per minute per access token (up to 20 numbers each).
                It's meant for checking real recipients, not for scanning lists of numbers.
            </p>
        </section>

        {{-- Code samples (for the send endpoint) --}}
        <section class="dc-section db-in" id="code-samples" style="--i: 9;">
            <h2 class="dc-h2"><span class="dc-h2-icon"><i class="bi bi-terminal"></i></span>Code samples: send a text message</h2>

            <div class="dc-code dc-code-tabs">
                <div class="dc-code-head">
                    <ul class="nav dc-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-curl" type="button" role="tab">curl</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-js" type="button" role="tab">JavaScript</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-php" type="button" role="tab">PHP</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-python" type="button" role="tab">Python</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-dotnet" type="button" role="tab">.NET (C#)</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-java" type="button" role="tab">Java</button>
                        </li>
                    </ul>
                    <button type="button" class="dc-copy" data-dc-copy-tab><i class="bi bi-clipboard"></i> Copy</button>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-curl" role="tabpanel">
<pre><code>curl -X POST {{ url('/api/v1/messages/send') }} \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "instance_id": "YOUR_INSTANCE_ID",
    "to": "919876543210",
    "message": "Hello from my WhatsApp Gateway!"
  }'</code></pre>
                    </div>
                    <div class="tab-pane fade" id="tab-js" role="tabpanel">
<pre><code>const response = await fetch("{{ url('/api/v1/messages/send') }}", {
  method: "POST",
  headers: {
    "Authorization": "Bearer YOUR_ACCESS_TOKEN",
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    instance_id: "YOUR_INSTANCE_ID",
    to: "919876543210",
    message: "Hello from my WhatsApp Gateway!",
  }),
});

const data = await response.json();
console.log(data);</code></pre>
                    </div>
                    <div class="tab-pane fade" id="tab-php" role="tabpanel">
<pre><code>$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => '{{ url('/api/v1/messages/send') }}',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'instance_id' => 'YOUR_INSTANCE_ID',
        'to' => '919876543210',
        'message' => 'Hello from my WhatsApp Gateway!',
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer YOUR_ACCESS_TOKEN',
    ],
]);

$response = curl_exec($curl);

if ($response === false) {
    // Couldn't reach the API at all (DNS, timeout, SSL, ...).
    echo 'Connection error: ' . curl_error($curl);
} else {
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $data = json_decode($response, true);

    if ($status === 200 &amp;&amp; ! empty($data['success'])) {
        echo 'Sent! Message ID: ' . $data['message_id'];
    } else {
        echo "Failed (HTTP $status): " . ($data['error'] ?? $response);
    }
}</code></pre>
                        <p class="dc-code-foot"><i class="bi bi-info-circle me-1"></i>Uses PHP's built-in <code>curl</code> extension (enabled on almost every host) — no Composer package needed.</p>
                    </div>
                    <div class="tab-pane fade" id="tab-python" role="tabpanel">
<pre><code>import requests

response = requests.post(
    "{{ url('/api/v1/messages/send') }}",
    headers={"Authorization": "Bearer YOUR_ACCESS_TOKEN"},
    json={
        "instance_id": "YOUR_INSTANCE_ID",
        "to": "919876543210",
        "message": "Hello from my WhatsApp Gateway!",
    },
)

data = response.json()
print(data)</code></pre>
                        <p class="dc-code-foot"><i class="bi bi-info-circle me-1"></i>Needs the <code>requests</code> package (<code>pip install requests</code>).</p>
                    </div>
                    <div class="tab-pane fade" id="tab-dotnet" role="tabpanel">
<pre><code>using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;

using var client = new HttpClient();
client.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", "YOUR_ACCESS_TOKEN");

var payload = new
{
    instance_id = "YOUR_INSTANCE_ID",
    to = "919876543210",
    message = "Hello from my WhatsApp Gateway!"
};

var content = new StringContent(JsonSerializer.Serialize(payload), Encoding.UTF8, "application/json");
var response = await client.PostAsync("{{ url('/api/v1/messages/send') }}", content);
var data = await response.Content.ReadAsStringAsync();

Console.WriteLine(data);</code></pre>
                        <p class="dc-code-foot"><i class="bi bi-info-circle me-1"></i>Uses only the .NET base class library (<code>System.Text.Json</code>) — no extra NuGet package needed.</p>
                    </div>
                    <div class="tab-pane fade" id="tab-java" role="tabpanel">
<pre><code>HttpClient client = HttpClient.newHttpClient();

String body = "{"
    + "\"instance_id\":\"YOUR_INSTANCE_ID\","
    + "\"to\":\"919876543210\","
    + "\"message\":\"Hello from my WhatsApp Gateway!\""
    + "}";

HttpRequest request = HttpRequest.newBuilder()
    .uri(URI.create("{{ url('/api/v1/messages/send') }}"))
    .header("Authorization", "Bearer YOUR_ACCESS_TOKEN")
    .header("Content-Type", "application/json")
    .POST(HttpRequest.BodyPublishers.ofString(body))
    .build();

HttpResponse&lt;String&gt; response = client.send(request, HttpResponse.BodyHandlers.ofString());
System.out.println(response.body());</code></pre>
                        <p class="dc-code-foot"><i class="bi bi-info-circle me-1"></i>Uses <code>java.net.http.HttpClient</code>, built into the JDK since Java 11 — no extra dependency.</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    // (Copy buttons are handled globally in resources/js/app.js.)

    // ---- Table of contents: highlight the section in view.
    const toc = document.querySelector('[data-dc-toc]');
    if (!toc || !('IntersectionObserver' in window)) {
        return;
    }

    const links = [...toc.querySelectorAll('[data-dc-target]')];
    const setActive = (id) => links.forEach((link) => {
        const on = link.dataset.dcTarget === id;
        link.classList.toggle('active', on);
        // On narrow screens the TOC is a horizontal bar: keep the active chip visible.
        if (on && toc.scrollWidth > toc.clientWidth) {
            toc.scrollTo({ left: link.offsetLeft - 16, behavior: 'smooth' });
        }
    });

    const observer = new IntersectionObserver((entries) => {
        entries.filter((entry) => entry.isIntersecting)
            .forEach((entry) => setActive(entry.target.id));
    }, { rootMargin: '-30% 0px -65% 0px' });

    links.forEach((link) => {
        const section = document.getElementById(link.dataset.dcTarget);
        if (section) {
            observer.observe(section);
        }
    });
})();
</script>
@endsection
