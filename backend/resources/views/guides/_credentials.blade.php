{{-- "Before you start": the account, instance and credentials every guide needs. --}}
<h2 class="h4 fw-bold mt-5 mb-3" id="before-you-start">Before you start</h2>
<p>You need three things from your {{ config('app.name') }} dashboard. It takes about five minutes:</p>
<ol class="mb-3">
    <li class="mb-2">
        <strong>A free account.</strong>
        @guest <a href="{{ route('register') }}">Sign up here</a> &mdash; no card needed. @else You're already signed in. @endguest
    </li>
    <li class="mb-2">
        <strong>A connected instance.</strong> Create an instance and scan its QR code with WhatsApp on your phone
        (<em>Settings &rarr; Linked devices &rarr; Link a device</em>), until its status shows <strong>Connected</strong>.
    </li>
    <li>
        <strong>Your <code>instance_id</code> and <code>access_token</code>.</strong> On the instance's page, generate an API
        credential. The token is shown <strong>only once</strong>, so copy it somewhere safe straight away.
    </li>
</ol>
<p>
    Keep both out of your code: store them as environment variables, so they never end up in Git.
    On macOS or Linux:
</p>
<x-code-block label="Terminal (macOS / Linux)">export INSTAMESSAGE_INSTANCE_ID="your-instance-id"
export INSTAMESSAGE_TOKEN="your-access-token"</x-code-block>
<p>On Windows (PowerShell):</p>
<x-code-block label="PowerShell (Windows)">$env:INSTAMESSAGE_INSTANCE_ID = "your-instance-id"
$env:INSTAMESSAGE_TOKEN = "your-access-token"</x-code-block>

<div class="alert alert-light border small">
    <i class="bi bi-telephone me-1"></i>
    <strong>Phone number format:</strong> country code first, digits only &mdash; no <code>+</code>, spaces or leading
    <code>0</code>. For example
    @if (\App\Support\Site::isSouthAfrica())
        <code>+27 82 123 4567</code> becomes <code>27821234567</code>.
    @else
        <code>+91 98765 43210</code> becomes <code>919876543210</code>.
    @endif
</div>
