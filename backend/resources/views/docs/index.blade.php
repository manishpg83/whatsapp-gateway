@extends('layouts.app')

@section('title', 'API Docs')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">API Docs</h1>
    <p class="text-muted mb-0">How to connect an instance, send WhatsApp messages and check their status through the API.</p>
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

{{-- Responsible use — shown before the endpoints on purpose. --}}
<div class="card shadow-sm mb-4 border-warning">
    <div class="card-body d-flex align-items-center gap-3 border-bottom">
        <div class="rounded-circle p-2 fs-4 lh-1 bg-warning-subtle text-warning-emphasis">
            <i class="bi bi-shield-exclamation"></i>
        </div>
        <div>
            <div class="fw-semibold">Protect your WhatsApp number</div>
            <div class="text-muted small">WhatsApp can restrict or ban a number that looks like it's spamming — and we can't undo that.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="small fw-semibold text-success mb-2"><i class="bi bi-check-circle me-1"></i>Do</div>
                <ul class="small mb-0 ps-3">
                    <li>Only message people who <strong>gave you their number</strong> and expect to hear from you.</li>
                    <li>Send messages people actually want: order updates, OTPs, appointment reminders, replies.</li>
                    <li>Keep sending volumes <strong>steady</strong> — build up gradually on a new number.</li>
                    <li>Personalise messages (name, order number) instead of sending one identical text to everyone.</li>
                    <li>Stop immediately when someone asks you to, and make it easy to ask.</li>
                    <li>Test with a number you can afford to lose.</li>
                </ul>
            </div>
            <div class="col-md-6">
                <div class="small fw-semibold text-danger mb-2"><i class="bi bi-x-circle me-1"></i>Don't</div>
                <ul class="small mb-0 ps-3">
                    <li>Send bulk promotional or marketing blasts to bought or scraped lists.</li>
                    <li>Send hundreds of messages in a sudden burst from a fresh number.</li>
                    <li>Message people who never contacted you or opted in.</li>
                    <li>Send the exact same message and link to large numbers of people.</li>
                    <li>Keep messaging numbers that don't reply or have blocked you.</li>
                </ul>
            </div>
        </div>
        <p class="small text-muted mb-0 mt-3">
            You are responsible for everything sent from your number — see
            <a href="{{ route('terms') }}#bulk-messaging">section 4 of our Terms</a>.
            This service does not offer, and will not build, anything designed to get around WhatsApp's limits.
        </p>
    </div>
</div>

{{-- Endpoint reference: every endpoint uses the same layout —
     method + path, description, request, responses, rate limit. --}}
<div class="card shadow-sm mb-4">
    <div class="card-body d-flex align-items-center gap-3 border-bottom">
        <div class="rounded-circle p-2 fs-4 lh-1" style="background-color: var(--wa-purple-light); color: var(--wa-purple);">
            <i class="bi bi-code-slash"></i>
        </div>
        <div class="fw-semibold">Endpoints</div>
    </div>

    {{-- Overview --}}
    <div class="card-body border-bottom">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 80px;">Method</th>
                        <th>Path</th>
                        <th>What it does</th>
                        <th class="text-nowrap">Rate limit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge text-bg-primary">POST</span></td>
                        <td><a href="#endpoint-send" class="text-decoration-none"><code>/api/v1/messages/send</code></a></td>
                        <td>Send a text, image, video, audio, voice note or document</td>
                        <td class="text-nowrap small">30 / min</td>
                    </tr>
                    <tr>
                        <td><span class="badge text-bg-success">GET</span></td>
                        <td><a href="#endpoint-status" class="text-decoration-none"><code>/api/v1/messages/{message_id}</code></a></td>
                        <td>Check a sent message's status</td>
                        <td class="text-nowrap small">60 / min</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-0 mt-2">Rate limits are per access token and counted separately for each endpoint.</p>
    </div>

    {{-- 1. Send a text message --}}
    <div class="card-body border-bottom" id="endpoint-send">
        <h5 class="h6 fw-semibold mb-2">Send a message (text or media)</h5>
        <p class="mb-0"><span class="badge text-bg-primary">POST</span> <code>/api/v1/messages/send</code></p>

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
                        <td><code>type</code> <span class="badge text-bg-light border">optional</span></td>
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
                        <td><code>media_url</code> <span class="badge text-bg-light border">media only</span></td>
                        <td>string (URL)</td>
                        <td>
                            A public <code>http(s)</code> link to the file. We download it, check it, and send it.
                            <strong>Every type except text needs either <code>media_url</code> or <code>media</code></strong> (not both).
                        </td>
                    </tr>
                    <tr>
                        <td><code>media</code> <span class="badge text-bg-light border">media only</span></td>
                        <td>file</td>
                        <td>
                            Upload the file directly instead of giving a link — send the request as
                            <code>multipart/form-data</code> (see the example below). Max upload on this server:
                            <strong>{{ ini_get('upload_max_filesize') }}</strong>.
                        </td>
                    </tr>
                    <tr>
                        <td><code>file_name</code> <span class="badge text-bg-light border">optional</span></td>
                        <td>string</td>
                        <td>For <code>document</code>: the name the recipient sees, e.g. <code>Invoice-42.pdf</code>. Defaults to the name in the URL.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h6 class="mt-3">Media rules</h6>
        <div class="table-responsive">
            <table class="table table-sm small">
                <thead>
                    <tr>
                        <th><code>type</code></th>
                        <th>Accepted files</th>
                        <th>Max size</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>image</code></td><td>JPG, PNG, WebP</td><td>5 MB</td></tr>
                    <tr><td><code>video</code></td><td>MP4, 3GP</td><td>16 MB</td></tr>
                    <tr><td><code>audio</code></td><td>MP3, OGG, M4A, AAC, AMR — sent as a music/audio file</td><td>16 MB</td></tr>
                    <tr><td><code>voice</code></td><td>OGG (Opus) only — shows as a playable voice note</td><td>16 MB</td></tr>
                    <tr><td><code>document</code></td><td>Any file (PDF, Word, Excel, ZIP, …)</td><td>100 MB</td></tr>
                </tbody>
            </table>
        </div>
        <p class="small text-muted">
            The file type is checked from the file's actual content, not its name. The link must be publicly reachable
            (no login) and respond within 30 seconds.
        </p>

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
                        <td><span class="badge text-bg-warning text-dark">422</span></td>
                        <td><code>{"success": false, "error": "The file at media_url is too large (max 5 MB for this type)."}</code></td>
                        <td>
                            <code>media_url</code> couldn't be used: not reachable, not public, too large, empty, or the wrong
                            file type for <code>type</code>. The <code>error</code> says which.
                        </td>
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
            Code samples in 6 languages are <a href="#code-samples">below</a>.
        </p>

        <h6 class="mt-3">Example: send a PDF with a caption</h6>
<pre class="bg-light rounded p-3 mb-0"><code>curl -X POST {{ url('/api/v1/messages/send') }} \
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

        <h6 class="mt-3">Example: upload a photo from your computer</h6>
<pre class="bg-light rounded p-3 mb-0"><code>curl -X POST {{ url('/api/v1/messages/send') }} \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Accept: application/json" \
  -F "instance_id=YOUR_INSTANCE_ID" \
  -F "to=919876543210" \
  -F "type=image" \
  -F "message=Here is the photo" \
  -F "media=@/path/to/photo.jpg"</code></pre>
    </div>

    {{-- 2. Check message status --}}
    <div class="card-body" id="endpoint-status">
        <h5 class="h6 fw-semibold mb-2">Check message status</h5>
        <p class="mb-0"><span class="badge text-bg-success">GET</span> <code>/api/v1/messages/{message_id}</code></p>

        <h6 class="mt-3">Request</h6>
        <p class="mb-0">
            No body. Put the <code>message_id</code> returned by <code>POST /api/v1/messages/send</code> in the URL
            and use the same access token. A token can only look up messages sent from its own instance.
        </p>

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
                        <td><code>{"success": true, "message": {...}}</code></td>
                        <td>Message found (full example below).</td>
                    </tr>
                    <tr>
                        <td><span class="badge text-bg-danger">401</span></td>
                        <td>&mdash;</td>
                        <td>Missing, unknown, or revoked access token.</td>
                    </tr>
                    <tr>
                        <td><span class="badge text-bg-warning text-dark">404</span></td>
                        <td><code>{"success": false, "error": "Message not found"}</code></td>
                        <td>No message with this ID was sent from this token's instance.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <h6>Example request</h6>
<pre class="bg-light rounded p-3 mb-0"><code>curl {{ url('/api/v1/messages/MESSAGE_ID') }} \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"</code></pre>

                <h6 class="mt-3"><code>status</code> values</h6>
                <ul class="small mb-0">
                    <li><code>pending</code> &mdash; being sent right now.</li>
                    <li><code>sent</code> &mdash; handed over to WhatsApp successfully (✓).</li>
                    <li><code>delivered</code> &mdash; reached the recipient's phone (✓✓). <code>delivered_at</code> is set.</li>
                    <li><code>read</code> &mdash; the recipient opened it (blue ✓✓). <code>read_at</code> is set.</li>
                    <li><code>failed</code> &mdash; could not be sent.</li>
                </ul>
                <p class="small text-muted mb-0">
                    <code>read</code> only appears if the recipient has read receipts turned on in WhatsApp.
                    Want updates pushed to you instead of polling? Set a webhook on your instance — you'll get a
                    <code>message.status</code> event each time a message is delivered or read.
                </p>
            </div>
            <div class="col-lg-6">
                <h6>Example response</h6>
<pre class="bg-light rounded p-3 mb-0"><code>{
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

        <p class="text-muted small mb-0 mt-3">
            <i class="bi bi-speedometer2 me-1"></i>Rate limit: 60 requests per minute per access token.
        </p>
    </div>
</div>

{{-- Code samples (for the send endpoint) --}}
<div class="card shadow-sm" id="code-samples">
    <div class="card-body d-flex align-items-center gap-3 border-bottom">
        <div class="bg-wa-light text-primary rounded-circle p-2 fs-4 lh-1">
            <i class="bi bi-terminal"></i>
        </div>
        <div class="fw-semibold">Code samples: send a text message</div>
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
<pre class="bg-light rounded p-3 mb-0"><code>$curl = curl_init();

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
                <p class="text-muted small mb-0 mt-2"><i class="bi bi-info-circle me-1"></i>Uses PHP's built-in <code>curl</code> extension (enabled on almost every host) — no Composer package needed.</p>
            </div>
            <div class="tab-pane fade" id="tab-python" role="tabpanel">
<pre class="bg-light rounded p-3 mb-0"><code>import requests

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
                <p class="text-muted small mb-0 mt-2"><i class="bi bi-info-circle me-1"></i>Needs the <code>requests</code> package (<code>pip install requests</code>).</p>
            </div>
            <div class="tab-pane fade" id="tab-dotnet" role="tabpanel">
<pre class="bg-light rounded p-3 mb-0"><code>using System.Net.Http.Headers;
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
                <p class="text-muted small mb-0 mt-2"><i class="bi bi-info-circle me-1"></i>Uses only the .NET base class library (<code>System.Text.Json</code>) — no extra NuGet package needed.</p>
            </div>
            <div class="tab-pane fade" id="tab-java" role="tabpanel">
<pre class="bg-light rounded p-3 mb-0"><code>HttpClient client = HttpClient.newHttpClient();

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
                <p class="text-muted small mb-0 mt-2"><i class="bi bi-info-circle me-1"></i>Uses <code>java.net.http.HttpClient</code>, built into the JDK since Java 11 — no extra dependency.</p>
            </div>
        </div>
    </div>
</div>
@endsection
