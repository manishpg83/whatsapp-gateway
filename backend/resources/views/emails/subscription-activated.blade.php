{{-- "You're subscribed" email (App\Notifications\SubscriptionActivated), Laravel's Markdown mail layout. --}}
<x-mail::message>
# Thank you, {{ $user->name }}!

Your payment was confirmed and your **{{ $plan['name'] }}** plan is now active. Your new limits apply straight away — no need to reconnect anything.

<x-mail::table>
| Your subscription | |
|:--|--:|
| Plan | **{{ $plan['name'] }}** |
| Price | ₹{{ number_format($plan['price']) }} / month |
| WhatsApp instances | {{ number_format($plan['instances']) }} |
| Messages per month | {{ number_format($plan['messages_per_month']) }} |
@if ($renewsOn)
| Next renewal | {{ $renewsOn->format('F j, Y') }} |
@endif
</x-mail::table>

<x-mail::button :url="route('dashboard')" color="success">
Go to your dashboard
</x-mail::button>

## What's next

- **Connect more numbers** — you can now run up to {{ number_format($plan['instances']) }} {{ Str::plural('instance', $plan['instances']) }} from the [Instances]({{ route('instances.index') }}) page.
- **Send more messages** — {{ number_format($plan['messages_per_month']) }} a month, and we'll email you at 80% so nothing stops by surprise.
- **Build with the API** — endpoints, webhooks and code examples are in the [API Docs]({{ route('docs.index') }}).

<x-mail::panel>
**About billing**<br>
Your plan renews automatically every month through Cashfree, our payment partner — we never see or store your card or UPI details. You can cancel anytime from your [Billing page]({{ route('billing.index') }}); cancelling stops future charges.
</x-mail::panel>

Questions about your subscription or payment? [Contact us]({{ route('contact', ['topic' => 'billing']) }}) — a real person replies within 1 business day.

Thanks for choosing us,<br>
The {{ config('app.name') }} team<br>
BriskBrain Technologies
</x-mail::message>
