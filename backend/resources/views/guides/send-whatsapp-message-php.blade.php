@extends('guides._layout')

@php($phone = \App\Support\Site::samplePhone())

@section('guide_body')
<p>
    In this tutorial you'll send a WhatsApp message from PHP using <strong>cURL</strong>, which comes with almost every PHP
    install, so there's no SDK or Composer package to add. By the end you'll have one small function that can send text,
    images and documents, report errors clearly, and check whether your message was delivered and read.
</p>
<p class="mb-0">It works with plain PHP 7.4 or newer, and drops straight into Laravel, WordPress or any framework.</p>

@include('guides._credentials')

<h2 class="h4 fw-bold mt-5 mb-3" id="helper">Step 1: Write one small helper function</h2>
<p>
    Every {{ config('app.name') }} request has the same shape: a URL, your access token in the <code>Authorization</code>
    header, and a JSON body. So we'll write that once. Save this as <code>instamessage.php</code>:
</p>
<x-code-block label="instamessage.php">&lt;?php

/**
 * Calls the {{ config('app.name') }} API and returns the decoded JSON.
 * Throws an exception with a readable message if anything goes wrong.
 */
function instamessage(string $method, string $path, ?array $body = null): array
{
    $curl = curl_init('{{ url('/api/v1') }}' . $path);

    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60, // media downloads can take a few seconds
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . getenv('INSTAMESSAGE_TOKEN'),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ];

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException("Could not reach the API: $error");
    }

    $data = json_decode($response, true) ?? [];

    if ($status !== 200 || empty($data['success'])) {
        throw new RuntimeException($data['error'] ?? "Request failed with HTTP $status");
    }

    return $data;
}</x-code-block>
<p>
    The function treats anything other than <code>200</code> with <code>"success": true</code> as a failure, and uses the
    API's own <code>error</code> text when there is one, so you see exactly what went wrong.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="send-text">Step 2: Send your first text message</h2>
<p>Create <code>send.php</code> next to it. Replace the number with your own WhatsApp number to test:</p>
<x-code-block label="send.php">&lt;?php

require __DIR__ . '/instamessage.php';

$result = instamessage('POST', '/messages/send', [
    'instance_id' => getenv('INSTAMESSAGE_INSTANCE_ID'),
    'to' => '{{ $phone }}',
    'message' => 'Hello from PHP! 👋',
]);

echo 'Sent! Message ID: ' . $result['message_id'] . PHP_EOL;</x-code-block>
<p>Run it from the terminal:</p>
<x-code-block label="Terminal">php send.php</x-code-block>
<p>A successful call prints the message ID, and the message arrives on the phone within a second or two. The API's full reply looks like this:</p>
<x-code-block label="Response · JSON">{
    "success": true,
    "message_id": "3EB0A1B2C3D4E5F6",
    "sent_via": "device",
    "fallback_status": null
}</x-code-block>

<h2 class="h4 fw-bold mt-5 mb-3" id="errors">Step 3: Handle errors</h2>
<p>Wrap the call in <code>try</code> / <code>catch</code> so one failed message doesn't stop your script:</p>
<x-code-block label="PHP">try {
    $result = instamessage('POST', '/messages/send', [
        'instance_id' => getenv('INSTAMESSAGE_INSTANCE_ID'),
        'to' => '{{ $phone }}',
        'message' => 'Your order #1042 has shipped.',
    ]);
} catch (RuntimeException $e) {
    error_log('WhatsApp message failed: ' . $e->getMessage());
}</x-code-block>
<p>The errors you're most likely to meet:</p>
<ul>
    <li><strong>HTTP 401</strong> &mdash; the access token is missing, wrong or revoked.</li>
    <li><strong>"Instance is not connected"</strong> &mdash; open your dashboard and reconnect the instance.</li>
    <li><strong>"instance_id does not match this token"</strong> &mdash; the token belongs to a different instance.</li>
    <li><strong>"You've reached your plan's monthly message limit"</strong> &mdash; upgrade, or wait for next month.</li>
    <li><strong>HTTP 429</strong> &mdash; too many requests; sending is limited to 30 per minute per token.</li>
</ul>

<h2 class="h4 fw-bold mt-5 mb-3" id="media">Step 4: Send an image or a PDF</h2>
<p>
    Add a <code>type</code> and a public <code>media_url</code>. {{ config('app.name') }} downloads the file, checks it and
    sends it. The <code>message</code> becomes an optional caption:
</p>
<x-code-block label="PHP">// An image with a caption (JPG, PNG or WebP, up to 5 MB)
instamessage('POST', '/messages/send', [
    'instance_id' => getenv('INSTAMESSAGE_INSTANCE_ID'),
    'to' => '{{ $phone }}',
    'type' => 'image',
    'media_url' => 'https://example.com/images/new-arrival.jpg',
    'message' => 'Just in: our new collection',
]);

// A PDF document, with the file name the recipient will see
instamessage('POST', '/messages/send', [
    'instance_id' => getenv('INSTAMESSAGE_INSTANCE_ID'),
    'to' => '{{ $phone }}',
    'type' => 'document',
    'media_url' => 'https://example.com/invoices/42.pdf',
    'file_name' => 'Invoice-42.pdf',
    'message' => 'Here is your invoice.',
]);</x-code-block>
<p>
    Video, audio and voice notes work the same way &mdash; see the <a href="{{ route('docs.index') }}#endpoint-send">media rules</a>
    for every type and size limit.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="status">Step 5: Check if it was delivered and read</h2>
<p>Use the <code>message_id</code> from step 2 to look up the message's status at any time:</p>
<x-code-block label="PHP">$status = instamessage('GET', '/messages/' . $result['message_id']);

echo $status['message']['status'] . PHP_EOL; // pending, sent, delivered, read or failed</x-code-block>
<p class="mb-0">
    <code>read</code> only appears if the recipient has read receipts turned on. To be told the moment a message is
    delivered or read, instead of asking, set a webhook on your instance.
</p>
@endsection
