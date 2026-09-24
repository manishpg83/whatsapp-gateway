@extends('layouts.app')

@section('title', 'Terms of Service')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="mb-4">
            <h1 class="h3 mb-1">Terms of Service</h1>
            <p class="text-muted mb-0">Last updated: {{ $lastUpdated->format('F j, Y') }}</p>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5">
                <p>
                    These Terms of Service ("Terms") govern your access to and use of
                    {{ config('app.name') }} (the "Service"), operated by BriskBrain Technologies
                    ("we", "us", "our"). By creating an account or using the Service, you agree
                    to be bound by these Terms. If you do not agree, do not use the Service.
                </p>

                <h2 class="h5 mt-4">1. What the Service is</h2>
                <p>
                    {{ config('app.name') }} lets you connect a WhatsApp number to your own
                    account and send and receive WhatsApp messages through a REST API, using
                    a WhatsApp Web-style device session.
                </p>
                <div class="alert alert-warning">
                    <strong>Important:</strong> {{ config('app.name') }} is an independent service
                    and is <strong>not affiliated with, endorsed by, or officially connected to
                    WhatsApp or Meta Platforms, Inc.</strong> in any way. It connects to WhatsApp
                    the same way the regular WhatsApp Web application does — it does not use
                    WhatsApp's official Business API. WhatsApp's own Terms of Service, Business
                    Policy, and enforcement systems apply to any number you connect, independently
                    of these Terms. WhatsApp may restrict, rate-limit, or ban a number for reasons
                    outside our control (for example, unusual sending patterns or user reports),
                    and we cannot prevent or reverse that. Do not connect a number you cannot
                    afford to lose access to.
                </div>

                <h2 class="h5 mt-4">2. Your account</h2>
                <p>
                    You must provide accurate information when registering and are responsible
                    for keeping your password, API access tokens, and any connected WhatsApp
                    session secure. You are responsible for all activity that happens under your
                    account and your API tokens, whether or not you personally performed it.
                    Tell us immediately if you believe your account or a token has been
                    compromised — you can also revoke a token yourself at any time from your
                    instance's page.
                </p>

                <h2 class="h5 mt-4">3. Acceptable use</h2>
                <p>You agree not to use the Service to:</p>
                <ul>
                    <li>Send unsolicited bulk messages, spam, or messages to recipients who have not agreed to receive them;</li>
                    <li>Violate WhatsApp's own Terms of Service or Business Policy;</li>
                    <li>Send content that is illegal, fraudulent, threatening, harassing, or infringes someone else's rights;</li>
                    <li>Attempt to bypass, disable, or interfere with WhatsApp's security, rate limits, CAPTCHA, or account-enforcement systems;</li>
                    <li>Attempt to access another user's account, instance, messages, or tokens;</li>
                    <li>Interfere with or disrupt the Service's infrastructure, or attempt to reverse-engineer it beyond what applicable law allows.</li>
                </ul>
                <p>
                    We may suspend or terminate access for any account we reasonably believe is
                    violating this section, with or without notice.
                </p>

                <h2 class="h5 mt-4" id="bulk-messaging">4. Bulk messaging and blocked numbers — your responsibility</h2>
                <div class="alert alert-danger">
                    <strong>If WhatsApp blocks or bans your number, that is not our responsibility.</strong>
                    You alone decide what you send, to whom, and how often. Sending bulk or
                    promotional messages, messaging people who haven't asked to hear from you, or
                    sending too many messages too quickly can get your WhatsApp number restricted
                    or permanently banned by WhatsApp — and we have no way to prevent or undo that.
                </div>
                <p>By using the Service, you understand and agree that:</p>
                <ul>
                    <li>
                        <strong>You are solely responsible for every message sent</strong> from your
                        connected number, whether sent through the dashboard, the API, or any tool or
                        integration you connect to it.
                    </li>
                    <li>
                        <strong>We are not liable</strong> for any restriction, suspension, or
                        permanent ban of your WhatsApp number or account by WhatsApp or Meta, or for
                        any loss that follows from it — including lost contacts, chats, customers,
                        sales, or business.
                    </li>
                    <li>
                        <strong>No refunds are given</strong> because a number was restricted or
                        banned. Your subscription remains active, and you may connect a different
                        number to your instance.
                    </li>
                    <li>
                        <strong>You must only message people who have agreed to hear from you</strong>
                        (for example, your own customers who gave you their number), and you must
                        honour anyone who asks you to stop.
                    </li>
                    <li>
                        The Service does not offer — and will not build — any feature designed to
                        avoid WhatsApp's limits or detection. Sending responsibly is the only way to
                        protect your number.
                    </li>
                </ul>
                <p class="small text-muted">
                    Good practice: send only messages people expect, keep volumes steady rather than
                    in sudden bursts, avoid identical messages to many recipients, and use a number
                    you can afford to lose while you test.
                </p>

                <h2 class="h5 mt-4">5. Subscriptions, billing, and cancellation</h2>
                <p>
                    Some features require a paid plan, billed monthly in advance through our
                    payment processor, Cashfree. By subscribing, you authorize us to charge your
                    chosen payment method on a recurring basis until you cancel. You can cancel
                    your subscription at any time from your account's Billing page, or by
                    contacting us; cancellation stops future billing but does not refund amounts
                    already charged for the current period, except where required by law. Fees
                    are shown in Indian Rupees (INR) and are exclusive of any taxes we're required
                    to collect. We may change plan pricing going forward; we'll give you
                    reasonable notice before a price change applies to your existing subscription.
                </p>

                <h2 class="h5 mt-4">6. Your content and data</h2>
                <p>
                    You keep ownership of the messages and data you send or receive through the
                    Service. We process that content — including message text and phone numbers
                    — only as needed to operate the Service (for example, delivering a message
                    you asked us to send, or forwarding an incoming message to a webhook URL you
                    configured). We do not sell your data. If you connect a webhook URL, you are
                    responsible for how that endpoint stores and handles the data we send it.
                    Deleting your account deletes your instances, API tokens, and message history
                    from our systems, other than what we're legally required to retain.
                    See our <a href="{{ route('privacy') }}">Privacy Policy</a> for details.
                </p>

                <h2 class="h5 mt-4">7. Service availability</h2>
                <p>
                    The Service is provided on an "as is" and "as available" basis. Because it
                    depends on an unofficial connection to WhatsApp's own infrastructure, we
                    cannot guarantee uninterrupted availability, message delivery, or that
                    WhatsApp won't change something on their end that affects the Service. We may
                    modify, suspend, or discontinue any part of the Service at any time.
                </p>

                <h2 class="h5 mt-4">8. Termination</h2>
                <p>
                    You may stop using the Service and delete your account at any time from your
                    Account page. We may suspend or terminate your access if you violate these
                    Terms, if required by law, or if we discontinue the Service, in which case
                    we'll try to give you reasonable notice where practical.
                </p>

                <h2 class="h5 mt-4">9. Disclaimers and limitation of liability</h2>
                <p>
                    To the fullest extent permitted by law, we disclaim all warranties, express
                    or implied, regarding the Service, including any warranty that it will be
                    uninterrupted, error-free, or that any WhatsApp number will remain connected
                    or unrestricted by WhatsApp. We are not liable for any loss or damage arising
                    from WhatsApp restricting, rate-limiting, or banning a number connected
                    through the Service, from messages not being delivered, or from any indirect,
                    incidental, or consequential damages. Our total liability for any claim
                    relating to the Service is limited to the amount you paid us in the three
                    months before the claim arose.
                </p>

                <h2 class="h5 mt-4">10. Changes to these Terms</h2>
                <p>
                    We may update these Terms from time to time. If we make a material change,
                    we'll update the date at the top of this page. Continuing to use the Service
                    after a change means you accept the updated Terms.
                </p>

                <h2 class="h5 mt-4">11. Governing law</h2>
                <p>
                    These Terms are governed by the laws of India, without regard to its
                    conflict-of-law principles.
                </p>

                <h2 class="h5 mt-4">12. Contact</h2>
                <p class="mb-0">
                    Questions about these Terms? Contact us at
                    <a href="mailto:briskbraintechnologies@gmail.com">briskbraintechnologies@gmail.com</a>.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
