@extends('guides._layout')

@php($phone = \App\Support\Site::samplePhone())

@section('guide_body')
<p>
    Here's how to send a WhatsApp message from Node.js using the <strong>fetch</strong> function that's built into
    Node.js 18 and newer &mdash; no npm packages to install. You'll end up with a small module you can use from an
    Express or Next.js app, a background worker or a one-off script, to send text, images and documents and track
    delivery.
</p>
<p class="mb-0">Check your version with <code>node --version</code>; you need 18 or newer.</p>

@include('guides._credentials')

<h2 class="h4 fw-bold mt-5 mb-3" id="module">Step 1: Write a small module</h2>
<p>
    Save this as <code>instamessage.mjs</code>. It adds your token to every request and throws an error with the API's
    own message when something goes wrong:
</p>
<x-code-block label="instamessage.mjs">const API_URL = "{{ url('/api/v1') }}";
const INSTANCE_ID = process.env.INSTAMESSAGE_INSTANCE_ID;
const TOKEN = process.env.INSTAMESSAGE_TOKEN;

async function request(method, path, body) {
  const response = await fetch(API_URL + path, {
    method,
    headers: {
      Authorization: "Bearer " + TOKEN,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: body ? JSON.stringify(body) : undefined,
    // Media files are downloaded before sending, so allow up to 60 seconds.
    signal: AbortSignal.timeout(60_000),
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok || !data.success) {
    throw new Error(data.error ?? "Request failed with HTTP " + response.status);
  }

  return data;
}

// Send a WhatsApp message. Extra fields (type, media_url, file_name) are passed through.
export function sendMessage(to, message, extra = {}) {
  return request("POST", "/messages/send", { instance_id: INSTANCE_ID, to, message, ...extra });
}

// Look up a sent message, including its delivery status.
export async function getMessage(messageId) {
  const data = await request("GET", "/messages/" + encodeURIComponent(messageId));
  return data.message;
}</x-code-block>
<p class="small text-muted">
    The <code>.mjs</code> ending lets you use <code>import</code> / <code>export</code> without changing your
    <code>package.json</code>.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="send-text">Step 2: Send your first text message</h2>
<p>Create <code>send.mjs</code> with your own WhatsApp number to test it:</p>
<x-code-block label="send.mjs">import { sendMessage } from "./instamessage.mjs";

const result = await sendMessage("{{ $phone }}", "Hello from Node.js! 👋");
console.log("Sent! Message ID:", result.message_id);</x-code-block>
<x-code-block label="Terminal">node send.mjs</x-code-block>
<p>
    Within a second or two the message lands on the phone. <code>result</code> holds the API's reply, for example
    <code>{ success: true, message_id: "3EB0A1B2C3D4E5F6", sent_via: "device", fallback_status: null }</code>.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="errors">Step 3: Handle errors</h2>
<p>Use <code>try</code> / <code>catch</code> &mdash; the error message is the API's explanation, or the network problem:</p>
<x-code-block label="JavaScript">import { sendMessage } from "./instamessage.mjs";

try {
  await sendMessage("{{ $phone }}", "Your OTP is 482913. It expires in 10 minutes.");
} catch (error) {
  console.error("WhatsApp message failed:", error.message);
}</x-code-block>
<p>What usually causes an error:</p>
<ul>
    <li><strong>HTTP 401</strong> &mdash; the access token is missing, wrong or revoked.</li>
    <li><strong>"Instance is not connected"</strong> &mdash; reconnect the instance from your dashboard.</li>
    <li><strong>"instance_id does not match this token"</strong> &mdash; the token is for a different instance.</li>
    <li><strong>"You've reached your plan's monthly message limit"</strong> &mdash; upgrade, or wait for the new month.</li>
    <li><strong>HTTP 429</strong> &mdash; more than 30 send requests in a minute; wait and try again.</li>
</ul>

<h2 class="h4 fw-bold mt-5 mb-3" id="media">Step 4: Send an image or a document</h2>
<p>Pass a <code>type</code> and a public <code>media_url</code> as extra fields. The text becomes an optional caption:</p>
<x-code-block label="JavaScript">import { sendMessage } from "./instamessage.mjs";

// An image with a caption (JPG, PNG or WebP, up to 5 MB)
await sendMessage("{{ $phone }}", "Just in: our new collection", {
  type: "image",
  media_url: "https://example.com/images/new-arrival.jpg",
});

// A PDF, with the file name the recipient will see
await sendMessage("{{ $phone }}", "Here is your invoice.", {
  type: "document",
  media_url: "https://example.com/invoices/42.pdf",
  file_name: "Invoice-42.pdf",
});</x-code-block>
<p>
    Videos, audio and voice notes work the same way &mdash; the <a href="{{ route('docs.index') }}#endpoint-send">media rules</a>
    list every type and size limit.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="status">Step 5: Check if it was delivered and read</h2>
<x-code-block label="JavaScript">import { getMessage, sendMessage } from "./instamessage.mjs";

const result = await sendMessage("{{ $phone }}", "Did you get this?");

const message = await getMessage(result.message_id);
console.log(message.status); // pending, sent, delivered, read or failed</x-code-block>
<p class="mb-0">
    <code>read</code> only appears when the recipient has read receipts turned on. To get delivery and read updates
    pushed to your server as they happen, set a webhook on your instance.
</p>
@endsection
