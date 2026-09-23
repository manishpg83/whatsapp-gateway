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

                <h2 class="h5 mt-4">4. Subscriptions, billing, and cancellation</h2>
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

                <h2 class="h5 mt-4">5. Your content and data</h2>
                <p>
                    You keep ownership of the messages and data you send or receive through the
                    Service. We process that content — including message text and phone numbers
                    — only as needed to operate the Service (for example, delivering a message
                    you asked us to send, or forwarding an incoming message to a webhook URL you
                    configured). We do not sell your data. If you connect a webhook URL, you are
                    responsible for how that endpoint stores and handles the data we send it.
                    Deleting your account deletes your instances, API tokens, and message history
                    from our systems, other than what we're legally required to retain.
                </p>

                <h2 class="h5 mt-4">6. Service availability</h2>
                <p>
                    The Service is provided on an "as is" and "as available" basis. Because it
                    depends on an unofficial connection to WhatsApp's own infrastructure, we
                    cannot guarantee uninterrupted availability, message delivery, or that
                    WhatsApp won't change something on their end that affects the Service. We may
                    modify, suspend, or discontinue any part of the Service at any time.
                </p>

                <h2 class="h5 mt-4">7. Termination</h2>
                <p>
                    You may stop using the Service and delete your account at any time from your
                    Account page. We may suspend or terminate your access if you violate these
                    Terms, if required by law, or if we discontinue the Service, in which case
                    we'll try to give you reasonable notice where practical.
                </p>

                <h2 class="h5 mt-4">8. Disclaimers and limitation of liability</h2>
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

                <h2 class="h5 mt-4">9. Changes to these Terms</h2>
                <p>
                    We may update these Terms from time to time. If we make a material change,
                    we'll update the date at the top of this page. Continuing to use the Service
                    after a change means you accept the updated Terms.
                </p>

                <h2 class="h5 mt-4">10. Governing law</h2>
                <p>
                    These Terms are governed by the laws of India, without regard to its
                    conflict-of-law principles.
                </p>

                <h2 class="h5 mt-4">11. Contact</h2>
                <p class="mb-0">
                    Questions about these Terms? Contact us at
                    <a href="mailto:briskbraintechnologies@gmail.com">briskbraintechnologies@gmail.com</a>.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
