{{-- Welcome email (App\Notifications\WelcomeUser), Laravel's Markdown mail layout. --}}
<x-mail::message>
# Welcome aboard, {{ $user->name }}!

Your email is verified and your **{{ config('app.name') }}** account is ready. You can now connect your own WhatsApp number and send & receive WhatsApp messages from your app through a simple REST API — no Business API approval, no new phone number.

## Get started in 3 steps

**1. Create an instance**<br>
An instance is one WhatsApp connection. Give it a name (for example "Support" or "Shop") from the Instances page.

**2. Scan the QR code**<br>
On your phone, open WhatsApp → **Settings → Linked devices → Link a device**, and scan the code — just like WhatsApp Web. The instance turns **Connected** within seconds.

**3. Generate your API token and send**<br>
On the instance page, create an API token, then send your first message with one HTTP request. The API Docs have ready-to-copy examples for curl, PHP, Python, JavaScript, Java and .NET.

<x-mail::button :url="route('dashboard')" color="success">
Go to your dashboard
</x-mail::button>

## What you can do

- **Send** text, images, videos, audio, voice notes and documents
- **Receive** incoming messages on your own server with webhooks
- **Track** every message's delivery and read status
- **Check** whether a phone number is on WhatsApp before you message it

<x-mail::panel>
**Your plan: {{ $plan['name'] }}**<br>
{{ $plan['instances'] }} {{ Str::plural('instance', $plan['instances']) }} · {{ number_format($plan['messages_per_month']) }} messages a month.<br>
We'll email you when you reach 80% of your monthly messages, so nothing stops by surprise. Need more? [Upgrade anytime]({{ route('billing.index') }}).
</x-mail::panel>

## A quick tip to protect your number

WhatsApp may restrict numbers that send spam. Only message people who expect to hear from you, keep your sending steady rather than in sudden bursts, and test with a number you can afford to lose. [Read more]({{ route('terms') }}#bulk-messaging).

## Helpful links

- [API Docs]({{ route('docs.index') }}) — endpoints, webhooks and code examples
- [Instances]({{ route('instances.index') }}) — connect and manage your numbers
- [Contact us]({{ route('contact') }}) — a real person replies within 1 business day

Happy building!

The {{ config('app.name') }} team<br>
BriskBrain Technologies
</x-mail::message>
