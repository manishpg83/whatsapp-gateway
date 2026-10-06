@extends('guides._layout')

@php($phone = \App\Support\Site::samplePhone())

@section('guide_body')
<p>
    This tutorial shows how to send a WhatsApp message from Python with the popular <strong>requests</strong> library.
    You'll build a tiny client you can import anywhere &mdash; a script, a Django or Flask app, a cron job or a Jupyter
    notebook &mdash; and use it to send text, images and documents, and to check delivery status.
</p>
<p class="mb-0">You need Python 3.8 or newer.</p>

@include('guides._credentials')

<h2 class="h4 fw-bold mt-5 mb-3" id="install">Step 1: Install requests</h2>
<x-code-block label="Terminal">pip install requests</x-code-block>

<h2 class="h4 fw-bold mt-5 mb-3" id="client">Step 2: Write a small client</h2>
<p>
    Save this as <code>instamessage.py</code>. It reads your credentials from the environment, adds your token to every
    request, and raises a clear error if the API says no:
</p>
<x-code-block label="instamessage.py">import os

import requests

API_URL = "{{ url('/api/v1') }}"
INSTANCE_ID = os.environ["INSTAMESSAGE_INSTANCE_ID"]

session = requests.Session()
session.headers.update({
    "Authorization": f"Bearer {os.environ['INSTAMESSAGE_TOKEN']}",
    "Accept": "application/json",
})


class InstaMessageError(Exception):
    pass


def _check(response):
    data = response.json() if response.content else {}
    if response.status_code != 200 or not data.get("success"):
        raise InstaMessageError(data.get("error") or f"Request failed with HTTP {response.status_code}")
    return data


def send_message(to, message=None, **extra):
    """Send a WhatsApp message. Extra fields (type, media_url, file_name) are passed through."""
    payload = {"instance_id": INSTANCE_ID, "to": to, **extra}
    if message is not None:
        payload["message"] = message
    # A long timeout, because the API may download a media file first.
    return _check(session.post(f"{API_URL}/messages/send", json=payload, timeout=60))


def get_message(message_id):
    """Look up a sent message, including its delivery status."""
    return _check(session.get(f"{API_URL}/messages/{message_id}", timeout=30))["message"]</x-code-block>

<h2 class="h4 fw-bold mt-5 mb-3" id="send-text">Step 3: Send your first text message</h2>
<p>Create <code>send.py</code> and put your own WhatsApp number in to test it:</p>
<x-code-block label="send.py">from instamessage import send_message

result = send_message("{{ $phone }}", "Hello from Python! 👋")
print("Sent! Message ID:", result["message_id"])</x-code-block>
<x-code-block label="Terminal">python send.py</x-code-block>
<p>
    The message arrives within a second or two. <code>result</code> is the API's reply as a dictionary, for example
    <code>{"success": True, "message_id": "3EB0A1B2C3D4E5F6", "sent_via": "device", "fallback_status": None}</code>.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="errors">Step 4: Handle errors</h2>
<p>Catch <code>InstaMessageError</code> for problems the API reports, and <code>requests.RequestException</code> for network trouble:</p>
<x-code-block label="Python">import requests

from instamessage import InstaMessageError, send_message

try:
    send_message("{{ $phone }}", "Your appointment is confirmed for 10:30 tomorrow.")
except InstaMessageError as error:
    print("The API refused the message:", error)
except requests.RequestException as error:
    print("Could not reach the API:", error)</x-code-block>
<p>Common reasons a message is refused:</p>
<ul>
    <li><strong>HTTP 401</strong> &mdash; the access token is missing, wrong or revoked.</li>
    <li><strong>"Instance is not connected"</strong> &mdash; reconnect the instance from your dashboard.</li>
    <li><strong>"instance_id does not match this token"</strong> &mdash; the token belongs to another instance.</li>
    <li><strong>"You've reached your plan's monthly message limit"</strong> &mdash; time to upgrade, or wait for next month.</li>
    <li><strong>HTTP 429</strong> &mdash; more than 30 send requests in a minute; slow down and retry.</li>
</ul>

<h2 class="h4 fw-bold mt-5 mb-3" id="media">Step 5: Send an image or a document</h2>
<p>Pass a <code>type</code> and a public <code>media_url</code>. The text becomes an optional caption:</p>
<x-code-block label="Python">from instamessage import send_message

# An image with a caption (JPG, PNG or WebP, up to 5 MB)
send_message(
    "{{ $phone }}",
    "Just in: our new collection",
    type="image",
    media_url="https://example.com/images/new-arrival.jpg",
)

# A PDF, with the file name the recipient will see
send_message(
    "{{ $phone }}",
    "Here is your invoice.",
    type="document",
    media_url="https://example.com/invoices/42.pdf",
    file_name="Invoice-42.pdf",
)</x-code-block>
<p>
    Videos, audio files and voice notes work the same way. The <a href="{{ route('docs.index') }}#endpoint-send">media rules</a>
    list every type and size limit.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="status">Step 6: Check if it was delivered and read</h2>
<x-code-block label="Python">from instamessage import get_message, send_message

result = send_message("{{ $phone }}", "Did you get this?")

message = get_message(result["message_id"])
print(message["status"])  # pending, sent, delivered, read or failed</x-code-block>
<p class="mb-0">
    <code>read</code> only shows up if the recipient has read receipts switched on. If you'd rather be notified than
    check, set a webhook on your instance and you'll receive an event each time a message is delivered or read.
</p>
@endsection
