@extends('guides._layout')

@php($phone = \App\Support\Site::samplePhone())

@section('guide_body')
<p>
    Sending is only half of a conversation. With a <strong>webhook</strong>, {{ config('app.name') }} calls a URL on your
    server the moment a WhatsApp message arrives on your number &mdash; text, photos, voice notes, documents &mdash; and
    again whenever a message you sent is delivered or read. No polling, no delay.
</p>
<p class="mb-0">
    This guide builds a small, secure receiver in <a href="#php">PHP</a>, <a href="#nodejs">Node.js</a> and
    <a href="#python">Python</a>, using only what comes built in. Pick the one you use.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="how-it-works">How it works</h2>
<ol>
    <li class="mb-2">Someone sends a WhatsApp message to your connected number.</li>
    <li class="mb-2">{{ config('app.name') }} sends an HTTP <code>POST</code> with a JSON body to your webhook URL.</li>
    <li class="mb-2">
        The request carries an <code>X-Webhook-Signature</code> header: <code>sha256=</code> followed by an HMAC-SHA256 of
        the raw body, made with your instance's <strong>signing secret</strong>. Checking it proves the request really came
        from {{ config('app.name') }}.
    </li>
    <li>Your server checks the signature, replies <code>200 OK</code>, and handles the message.</li>
</ol>
<p>An incoming message looks like this:</p>
<x-code-block label="message.received · JSON">{
    "event": "message.received",
    "instance_id": "YOUR_INSTANCE_ID",
    "from": "{{ $phone }}",
    "type": "text",
    "message": "Hi, is the blue one still in stock?",
    "message_id": "3EB0A1B2C3D4E5F6",
    "timestamp": "2026-10-06T10:15:03.000Z",
    "media": null
}</x-code-block>
<p>And when a message you sent is delivered or read:</p>
<x-code-block label="message.status · JSON">{
    "event": "message.status",
    "instance_id": "YOUR_INSTANCE_ID",
    "message_id": "3EB0A1B2C3D4E5F6",
    "status": "read",
    "to": "{{ $phone }}",
    "timestamp": "2026-10-06T10:17:41+00:00"
}</x-code-block>
<p>
    <code>type</code> can be <code>text</code>, <code>image</code>, <code>video</code>, <code>voice</code>, <code>audio</code>,
    <code>document</code>, <code>sticker</code>, <code>location</code>, <code>contact</code> or <code>unsupported</code>.
    For photos, videos, audio and documents, <code>message</code> is the caption and <code>media</code> holds the file details
    (see <a href="#media">media files</a> below).
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="before-you-start">Before you start</h2>
<ul>
    <li class="mb-2">
        A {{ config('app.name') }} account with a <strong>connected instance</strong>.
        @guest
            <a href="{{ route('register') }}">Sign up free</a> if you don't have one yet.
        @endguest
    </li>
    <li>
        Somewhere to run the receiver. To try it on your own computer first, keep reading &mdash; <a href="#go-live">step 2</a>
        shows how to make it reachable.
    </li>
</ul>
<p>Every example reads the signing secret from an environment variable, so it never ends up in your code:</p>
<x-code-block label="Terminal (macOS / Linux)">export INSTAMESSAGE_WEBHOOK_SECRET="your-signing-secret"</x-code-block>
<x-code-block label="PowerShell (Windows)">$env:INSTAMESSAGE_WEBHOOK_SECRET = "your-signing-secret"</x-code-block>
<p class="small text-muted">You'll copy the real secret from your instance page in step 3.</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="receiver">Step 1: Write the receiver</h2>
<p>
    All three versions do the same four things: read the <strong>raw</strong> body, check the signature in constant time,
    reply <code>200</code> straight away, then handle the event.
</p>

<h3 class="h5 fw-bold mt-4" id="php">PHP</h3>
<p>Save as <code>webhook.php</code>. It works on any PHP host, or locally with PHP's built-in server:</p>
<x-code-block label="webhook.php">&lt;?php

$secret = getenv('INSTAMESSAGE_WEBHOOK_SECRET');

// The signature is calculated over the exact bytes sent, so read the raw body.
$body = file_get_contents('php://input');
$expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
$received = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

if (! hash_equals($expected, $received)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($body, true);

switch ($event['event'] ?? '') {
    case 'message.received':
        error_log("New {$event['type']} from {$event['from']}: {$event['message']}");
        break;

    case 'message.status':
        error_log("Message {$event['message_id']} is now {$event['status']}");
        break;
}

echo 'OK';</x-code-block>
<x-code-block label="Terminal">php -S localhost:8080 webhook.php</x-code-block>
<p class="small text-muted">
    Using Laravel? Put the same logic in a controller, read the body with <code>$request->getContent()</code>, and leave the
    route out of CSRF checks: in <code>bootstrap/app.php</code> add
    <code>$middleware->validateCsrfTokens(except: ['whatsapp/webhook']);</code>
</p>

<h3 class="h5 fw-bold mt-4" id="nodejs">Node.js</h3>
<p>Save as <code>webhook.mjs</code>. It uses only Node's built-in modules, so there's nothing to install:</p>
<x-code-block label="webhook.mjs">import crypto from "node:crypto";
import http from "node:http";

const SECRET = process.env.INSTAMESSAGE_WEBHOOK_SECRET;

function isGenuine(body, signature) {
  const expected = Buffer.from("sha256=" + crypto.createHmac("sha256", SECRET).update(body).digest("hex"));
  const received = Buffer.from(signature ?? "");
  return received.length === expected.length && crypto.timingSafeEqual(received, expected);
}

http.createServer((req, res) => {
  const chunks = [];
  req.on("data", (chunk) => chunks.push(chunk));
  req.on("end", () => {
    const body = Buffer.concat(chunks); // the raw bytes, exactly as sent

    if (req.method !== "POST" || !isGenuine(body, req.headers["x-webhook-signature"])) {
      res.writeHead(401).end("Invalid signature");
      return;
    }

    res.writeHead(200).end("OK"); // answer fast, then do the work

    const event = JSON.parse(body.toString("utf8"));
    if (event.event === "message.received") {
      console.log(`New ${event.type} from ${event.from}: ${event.message}`);
    } else if (event.event === "message.status") {
      console.log(`Message ${event.message_id} is now ${event.status}`);
    }
  });
}).listen(8080, () => console.log("Waiting for webhooks on http://localhost:8080"));</x-code-block>
<x-code-block label="Terminal">node webhook.mjs</x-code-block>
<p class="small text-muted">
    Using Express? Read the body with <code>express.raw({ type: "application/json" })</code> on this route, not
    <code>express.json()</code> &mdash; the signature needs the original bytes.
</p>

<h3 class="h5 fw-bold mt-4" id="python">Python</h3>
<p>Save as <code>webhook.py</code>. It uses only Python's standard library:</p>
<x-code-block label="webhook.py">import hashlib
import hmac
import json
import os
import sys
from http.server import BaseHTTPRequestHandler, HTTPServer

SECRET = os.environ["INSTAMESSAGE_WEBHOOK_SECRET"].encode()

# WhatsApp messages are full of emoji; this stops print() failing on them on Windows.
sys.stdout.reconfigure(encoding="utf-8", errors="replace")


class WebhookHandler(BaseHTTPRequestHandler):
    def do_POST(self):
        # The raw bytes, exactly as sent: the signature is calculated over them.
        body = self.rfile.read(int(self.headers.get("Content-Length", 0)))

        expected = "sha256=" + hmac.new(SECRET, body, hashlib.sha256).hexdigest()
        received = self.headers.get("X-Webhook-Signature", "")
        if not hmac.compare_digest(expected.encode(), received.encode()):
            self.send_response(401)
            self.end_headers()
            return

        self.send_response(200)  # answer fast, then do the work
        self.end_headers()
        self.wfile.write(b"OK")

        event = json.loads(body)
        if event["event"] == "message.received":
            print(f"New {event['type']} from {event['from']}: {event['message']}")
        elif event["event"] == "message.status":
            print(f"Message {event['message_id']} is now {event['status']}")


print("Waiting for webhooks on http://localhost:8080")
HTTPServer(("", 8080), WebhookHandler).serve_forever()</x-code-block>
<x-code-block label="Terminal">python webhook.py</x-code-block>
<p class="small text-muted">
    Using Flask or Django? Read the body with <code>request.get_data()</code> (Flask) or <code>request.body</code> (Django)
    and check the signature exactly the same way.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="go-live">Step 2: Make it reachable</h2>
<p>
    {{ config('app.name') }} has to reach your receiver over the internet, so the URL must be public &mdash; use
    <code>https://</code>. On a real server, that's simply your domain, for example
    <code>https://your-app.example.com/whatsapp/webhook</code>.
</p>
<p>
    To test on your own computer, use a tunnelling tool such as <strong>ngrok</strong> or <strong>Cloudflare Tunnel</strong>:
    it gives you a temporary public <code>https</code> address that forwards to <code>localhost:8080</code>.
    Use the final address directly: redirects are not followed.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="connect">Step 3: Connect it to your instance</h2>
<ol>
    <li class="mb-2">Open your instance in the dashboard and find the <strong>Webhook</strong> section.</li>
    <li class="mb-2">Paste your URL into <strong>Webhook URL</strong> and click <strong>Save</strong>.</li>
    <li class="mb-2">
        Copy the <strong>Signing secret</strong> that appears, put it in <code>INSTAMESSAGE_WEBHOOK_SECRET</code>, and restart
        your receiver.
    </li>
    <li>
        Click <strong>Send test webhook</strong>. Your receiver gets a <code>webhook.test</code> event, and the delivery log
        on the instance page shows whether your server answered <code>200</code>.
    </li>
</ol>
<p class="mb-0">Then send a WhatsApp message to your connected number from another phone &mdash; it appears in your receiver's output.</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="media">Media files</h2>
<p>For photos, videos, voice notes and documents, the event includes a <code>media</code> object:</p>
<x-code-block label="media · JSON">"media": {
    "status": "stored",
    "mime_type": "image/jpeg",
    "file_name": null,
    "size": 184223,
    "url": "{{ url('/media/123') }}?expires=...&amp;signature=..."
}</x-code-block>
<p class="mb-0">
    <code>url</code> is a download link that works <strong>for 24 hours</strong> with no login, so download the file
    straight away if you want to keep it. It's only set when <code>status</code> is <code>stored</code>; it's
    <code>too_large</code> for files over 100 MB and <code>failed</code> if the download from WhatsApp didn't work.
    <code>file_name</code> is only set for documents.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="rules">Delivery rules to build for</h2>
<ul class="mb-0">
    <li class="mb-2">
        <strong>Answer within 5 seconds</strong> with any <code>2xx</code> status. Do slow work (database writes to other
        systems, sending replies) after answering, or in a background job.
    </li>
    <li class="mb-2">
        <strong>Failed deliveries are retried</strong> up to 5 times: after 10 seconds, 1 minute, 5 minutes, 15 minutes
        and 30 minutes. A timeout, an error status or a redirect counts as a failure.
    </li>
    <li class="mb-2">
        <strong>Expect the occasional duplicate.</strong> A retry can deliver an event you already handled, so skip any
        <code>message_id</code> you've seen before.
    </li>
    <li>
        <strong>Reject anything with a wrong signature</strong>, as the examples do. Never trust the body before the check.
    </li>
</ul>

<h2 class="h4 fw-bold mt-5 mb-3" id="reply">Reply automatically</h2>
<p class="mb-0">
    To answer a message from your code, call the send API with the sender's <code>from</code> number as <code>to</code> &mdash;
    see the <a href="{{ route('guides.show', 'send-whatsapp-message-php') }}">PHP</a>,
    <a href="{{ route('guides.show', 'send-whatsapp-message-python') }}">Python</a> or
    <a href="{{ route('guides.show', 'send-whatsapp-message-nodejs') }}">Node.js</a> guide. For simple keyword answers
    like prices and opening hours, you don't need code at all: switch on the built-in <strong>Chatbot</strong> in your
    dashboard.
</p>
@endsection
