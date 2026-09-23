@extends('layouts.app')

@section('title', 'API Docs')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">API Docs</h1>
    <p class="text-muted mb-0">How to connect an instance and send WhatsApp messages through the API.</p>
</div>

{{-- Getting started --}}
<div class="card shadow-sm mb-4">
    <div class="card-body d-flex align-items-center gap-3 border-bottom">
        <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
            <i class="bi bi-rocket-takeoff"></i>
        </div>
        <div class="fw-semibold">Getting started</div>
    </div>
    <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex align-items-start gap-3 py-3">
            <span class="step-number step-number-1">1</span>
            <span>
                <a href="{{ route('instances.create') }}">Create an instance</a> and scan its QR code with
                WhatsApp on your phone until its status shows <span class="badge text-bg-success">Connected</span>.
            </span>
        </li>
        <li class="list-group-item d-flex align-items-start gap-3 py-3">
            <span class="step-number step-number-2">2</span>
            <span>
                Open the connected instance's page and generate an <strong>API credential</strong>. You'll be
                shown an <code>instance_id</code> and an <code>access_token</code> — the token is shown
                <strong>once</strong>, so copy it somewhere safe immediately.
            </span>
        </li>
        <li class="list-group-item d-flex align-items-start gap-3 py-3">
            <span class="step-number step-number-3">3</span>
            <span>Call the API below with that <code>instance_id</code> and <code>access_token</code>.</span>
        </li>
    </ul>
</div>

{{-- Authentication --}}
<div class="card shadow-sm mb-4">
    <div class="card-body d-flex align-items-center gap-3 border-bottom">
        <div class="rounded-circle p-2 fs-4 lh-1" style="background-color: var(--wa-info-light); color: var(--wa-info);">
            <i class="bi bi-shield-lock"></i>
        </div>
        <div class="fw-semibold">Authentication</div>
    </div>
    <div class="card-body">
        <p>Send your access token as a Bearer token in the <code>Authorization</code> header:</p>
        <pre class="bg-light border rounded p-3 mb-2"><code>Authorization: Bearer YOUR_ACCESS_TOKEN</code></pre>
        <ul class="mb-0 text-muted small">
            <li>Tokens are tied to one instance. The <code>instance_id</code> in the request body must match the token.</li>
            <li>A revoked or unknown token gets a <code>401</code>.</li>
            <li>Never share your access token or commit it to a public repository.</li>
        </ul>
    </div>
</div>

{{-- Endpoint reference --}}
<div class="card shadow-sm mb-4">
    <div class="card-body d-flex align-items-center gap-3 border-bottom">
        <div class="rounded-circle p-2 fs-4 lh-1" style="background-color: var(--wa-purple-light); color: var(--wa-purple);">
            <i class="bi bi-code-slash"></i>
        </div>
        <div class="fw-semibold">Send a text message</div>
    </div>
    <div class="card-body">
        <p><span class="badge text-bg-primary">POST</span> <code>/api/v1/messages/send</code></p>

        <h6 class="mt-3">Request body</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Type</th>
                        <th>Description</th>
                    </tr>
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
                        <td>Recipient's phone number, digits only with country code (e.g. <code>919876543210</code>), 7-15 digits.</td>
                    </tr>
                    <tr>
                        <td><code>message</code></td>
                        <td>string</td>
                        <td>Plain text message, up to 4096 characters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h6 class="mt-3">Responses</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Body</th>
                        <th>Meaning</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge text-bg-success">200</span></td>
                        <td><code>{"success": true, "message_id": "..."}</code></td>
                        <td>Message sent.</td>
                    </tr>
                    <tr>
                        <td><span class="badge text-bg-danger">401</span></td>
                        <td>&mdash;</td>
                        <td>Missing, unknown, or revoked access token.</td>
                    </tr>
                    <tr>
                        <td><span class="badge text-bg-warning text-dark">422</span></td>
                        <td><code>{"success": false, "error": "instance_id does not match this token"}</code></td>
                        <td>The body's <code>instance_id</code> doesn't belong to this token.</td>
                    </tr>
                    <tr>
                        <td><span class="badge text-bg-warning text-dark">422</span></td>
                        <td><code>{"success": false, "error": "Instance is not connected"}</code></td>
                        <td>The instance isn't currently connected to WhatsApp.</td>
                    </tr>
                    <tr>
                        <td><span class="badge text-bg-warning text-dark">422</span></td>
                        <td><code>{"success": false, "error": "You've reached your plan's monthly message limit. Upgrade to send more."}</code></td>
                        <td>Your <a href="{{ route('billing.index') }}">plan's</a> monthly message quota is used up.</td>
                    </tr>
                    <tr>
                        <td><span class="badge text-bg-secondary">502</span></td>
                        <td><code>{"success": false, "error": "Could not send message"}</code></td>
                        <td>The message could not be delivered (e.g. invalid number).</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-muted small mb-0">
            <i class="bi bi-speedometer2 me-1"></i>Rate limit: 30 requests per minute per access token.
        </p>
    </div>
</div>

{{-- Code samples --}}
<div class="card shadow-sm">
    <div class="card-body d-flex align-items-center gap-3 border-bottom">
        <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
            <i class="bi bi-terminal"></i>
        </div>
        <div class="fw-semibold">Code samples</div>
    </div>
    <div class="card-body">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-curl" type="button" role="tab">curl</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-js" type="button" role="tab">JavaScript</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-php" type="button" role="tab">PHP</button>
            </li>
        </ul>
        <div class="tab-content border border-top-0 rounded-bottom p-3">
            <div class="tab-pane fade show active" id="tab-curl" role="tabpanel">
<pre class="bg-light rounded p-3 mb-0"><code>curl -X POST {{ url('/api/v1/messages/send') }} \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "instance_id": "YOUR_INSTANCE_ID",
    "to": "919876543210",
    "message": "Hello from my WhatsApp Gateway!"
  }'</code></pre>
            </div>
            <div class="tab-pane fade" id="tab-js" role="tabpanel">
<pre class="bg-light rounded p-3 mb-0"><code>const response = await fetch("{{ url('/api/v1/messages/send') }}", {
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
<pre class="bg-light rounded p-3 mb-0"><code>$response = file_get_contents("{{ url('/api/v1/messages/send') }}", false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Authorization: Bearer YOUR_ACCESS_TOKEN\r\n" .
                     "Content-Type: application/json\r\n",
        'content' => json_encode([
            'instance_id' => 'YOUR_INSTANCE_ID',
            'to' => '919876543210',
            'message' => 'Hello from my WhatsApp Gateway!',
        ]),
    ],
]));

$data = json_decode($response, true);</code></pre>
            </div>
        </div>
    </div>
</div>
@endsection
