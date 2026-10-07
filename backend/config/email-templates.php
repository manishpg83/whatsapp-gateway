<?php

/*
|--------------------------------------------------------------------------
| Email templates — built-in defaults
|--------------------------------------------------------------------------
|
| Every email we send to users. An admin can override the subject, body and
| button label from Admin → Email Templates (saved in the email_templates
| table); "Reset to default" deletes that row and the text below is used
| again. Rendering is done by App\Services\EmailTemplates.
|
| Bodies use only what the editor (Quill) supports: p, h1-h3, strong, em, u,
| s, a, ul/ol/li, blockquote. A blockquote is shown as a highlighted box.
|
| Placeholders are written as {name}. These are available in every
| template (see EmailTemplates::GLOBAL_PLACEHOLDERS): {name}, {app_name},
| {dashboard_url}, {instances_url}, {billing_url}, {docs_url},
| {contact_url}, {terms_url}. Put {button} on its own line in the body to
| choose where the button goes; without it, the button goes at the end.
|
| 'placeholders' lists the extra ones for that template, as
| name => [what it is, sample value used by the preview and test email].
|
*/

return [

    'verify_email' => [
        'label' => 'Verify email address',
        'sent_when' => 'Someone registers, clicks "resend", or changes their email address (sent to the new address).',
        'icon' => 'bi-envelope-check',
        'placeholders' => [
            'expire_minutes' => ['How many minutes the link works for', '60'],
        ],
        'subject' => 'Verify your email address',
        'button_text' => 'Verify email address',
        'body' => <<<'HTML'
<h1>Confirm your email, {name}</h1>
<p>Thanks for signing up for {app_name}! Please confirm this is your email address by clicking the button below.</p>
<p>{button}</p>
<p>This link expires in {expire_minutes} minutes. If you didn't create an account, you can safely ignore this email.</p>
HTML,
    ],

    'welcome' => [
        'label' => 'Welcome',
        'sent_when' => 'A new user verifies their email address for the first time.',
        'icon' => 'bi-stars',
        'placeholders' => [
            'plan_name' => ['Their current plan', 'Free'],
            'instances' => ['Instances included, e.g. "1 instance"', '1 instance'],
            'messages_per_month' => ['Messages included per month', '500'],
        ],
        'subject' => "Welcome to {app_name} — let's send your first message",
        'button_text' => 'Go to your dashboard',
        'body' => <<<'HTML'
<h1>Welcome aboard, {name}!</h1>
<p>Your email is verified and your <strong>{app_name}</strong> account is ready. You can now connect your own WhatsApp number and send &amp; receive WhatsApp messages from your app through a simple REST API — no Business API approval, no new phone number.</p>
<h2>Get started in 3 steps</h2>
<ol>
<li><strong>Create an instance.</strong> An instance is one WhatsApp connection. Give it a name (for example "Support" or "Shop") from the <a href="{instances_url}">Instances</a> page.</li>
<li><strong>Scan the QR code.</strong> On your phone, open WhatsApp → <strong>Settings → Linked devices → Link a device</strong>, and scan the code — just like WhatsApp Web. The instance turns <strong>Connected</strong> within seconds.</li>
<li><strong>Generate your API token and send.</strong> On the instance page, create an API token, then send your first message with one HTTP request. The <a href="{docs_url}">API Docs</a> have ready-to-copy examples for curl, PHP, Python, JavaScript, Java and .NET.</li>
</ol>
<p>{button}</p>
<h2>What you can do</h2>
<ul>
<li><strong>Send</strong> text, images, videos, audio, voice notes and documents</li>
<li><strong>Receive</strong> incoming messages on your own server with webhooks</li>
<li><strong>Track</strong> every message's delivery and read status</li>
<li><strong>Check</strong> whether a phone number is on WhatsApp before you message it</li>
</ul>
<blockquote><strong>Your plan: {plan_name}</strong> — {instances} · {messages_per_month} messages a month. We'll email you when you reach 80% of your monthly messages, so nothing stops by surprise. Need more? <a href="{billing_url}">Upgrade anytime</a>.</blockquote>
<h2>A quick tip to protect your number</h2>
<p>WhatsApp may restrict numbers that send spam. Only message people who expect to hear from you, keep your sending steady rather than in sudden bursts, and test with a number you can afford to lose. <a href="{terms_url}#bulk-messaging">Read more</a>.</p>
<h2>Helpful links</h2>
<ul>
<li><a href="{docs_url}">API Docs</a> — endpoints, webhooks and code examples</li>
<li><a href="{instances_url}">Instances</a> — connect and manage your numbers</li>
<li><a href="{contact_url}">Contact us</a> — a real person replies within 1 business day</li>
</ul>
<p>Happy building!</p>
<p>The {app_name} team · BriskBrain Technologies</p>
HTML,
    ],

    'password_reset' => [
        'label' => 'Password reset',
        'sent_when' => 'Someone asks for a password reset link on the "Forgot password" page.',
        'icon' => 'bi-key',
        'placeholders' => [
            'expire_minutes' => ['How many minutes the link works for', '60'],
        ],
        'subject' => 'Reset your password',
        'button_text' => 'Reset password',
        'body' => <<<'HTML'
<h1>Reset your password</h1>
<p>Hi {name},</p>
<p>We received a request to reset the password for your {app_name} account. Click the button below to choose a new one.</p>
<p>{button}</p>
<p>This link expires in {expire_minutes} minutes. If you didn't ask for a password reset, you can ignore this email — your password won't change.</p>
HTML,
    ],

    'email_changed' => [
        'label' => 'Email address changed',
        'sent_when' => 'A user changes their email address (sent to the OLD address, as a security alert).',
        'icon' => 'bi-shield-exclamation',
        'placeholders' => [
            'new_email' => ['The new address, partly hidden', 'ja***@example.com'],
        ],
        'subject' => 'Your email address was changed',
        'button_text' => 'Contact support',
        'body' => <<<'HTML'
<p>Hi {name},</p>
<p>The email address on your {app_name} account was just changed to <strong>{new_email}</strong>.</p>
<p>If you made this change, you can ignore this email.</p>
<p>If you did <strong>NOT</strong> make this change, someone may have access to your account. Contact us right away so we can help you secure it.</p>
<p>{button}</p>
HTML,
    ],

    'subscription_activated' => [
        'label' => 'Subscription activated',
        'sent_when' => 'A payment is confirmed and a paid plan becomes active.',
        'icon' => 'bi-bag-check',
        'placeholders' => [
            'plan_name' => ['The plan they bought', 'Starter'],
            'plan_price' => ['Monthly price', '₹749'],
            'instances' => ['Instances included, e.g. "3 instances"', '3 instances'],
            'messages_per_month' => ['Messages included per month', '10,000'],
            'renew_date' => ['Next renewal date', 'November 2, 2026'],
        ],
        'subject' => "You're subscribed to the {plan_name} plan",
        'button_text' => 'Go to your dashboard',
        'body' => <<<'HTML'
<h1>Thank you, {name}!</h1>
<p>Your payment was confirmed and your <strong>{plan_name}</strong> plan is now active. Your new limits apply straight away — no need to reconnect anything.</p>
<h2>Your subscription</h2>
<ul>
<li><strong>{plan_name}</strong> plan — {plan_price} / month</li>
<li>Up to {instances}</li>
<li>{messages_per_month} messages a month</li>
<li>Next renewal: {renew_date}</li>
</ul>
<p>{button}</p>
<h2>What's next</h2>
<ul>
<li><strong>Connect more numbers</strong> — you can now run up to {instances} from the <a href="{instances_url}">Instances</a> page.</li>
<li><strong>Send more messages</strong> — {messages_per_month} a month, and we'll email you at 80% so nothing stops by surprise.</li>
<li><strong>Build with the API</strong> — endpoints, webhooks and code examples are in the <a href="{docs_url}">API Docs</a>.</li>
</ul>
<blockquote><strong>About billing:</strong> Your plan renews automatically every month through Cashfree, our payment partner — we never see or store your card or UPI details. You can cancel anytime from your <a href="{billing_url}">Billing page</a>; cancelling stops future charges.</blockquote>
<p>Questions about your subscription or payment? <a href="{contact_url}">Contact us</a> — a real person replies within 1 business day.</p>
<p>Thanks for choosing us — the {app_name} team</p>
HTML,
    ],

    'subscription_cancelled' => [
        'label' => 'Subscription cancelled',
        'sent_when' => 'A user cancels their plan, or Cashfree reports the subscription ended.',
        'icon' => 'bi-x-circle',
        'placeholders' => [
            'plan_name' => ['The plan that was cancelled', 'Starter'],
        ],
        'subject' => 'Your {plan_name} subscription has been cancelled',
        'button_text' => 'Go to Billing',
        'body' => <<<'HTML'
<p>Hi {name},</p>
<p>Your {plan_name} subscription has been cancelled. No further payments will be taken for it.</p>
<p>Your account is now on the Free plan limits. Your instances, API tokens and message history are kept.</p>
<p>Changed your mind? You can subscribe again anytime from the Billing page.</p>
<p>{button}</p>
<p>If you didn't cancel this yourself, please <a href="{contact_url}">contact us</a>.</p>
HTML,
    ],

    'renewal_reminder' => [
        'label' => 'Renewal reminder',
        'sent_when' => 'A few days before a paid plan renews automatically.',
        'icon' => 'bi-calendar-event',
        'placeholders' => [
            'plan_name' => ['The plan that renews', 'Starter'],
            'amount' => ['Amount that will be charged', '₹749'],
            'renew_date' => ['Renewal date', 'October 30, 2026'],
        ],
        'subject' => 'Your {plan_name} plan renews on {renew_date}',
        'button_text' => 'Manage your plan',
        'body' => <<<'HTML'
<p>Hi {name},</p>
<p>This is a reminder that your {plan_name} plan renews automatically on <strong>{renew_date}</strong>.</p>
<p>{amount} will be charged through Cashfree using the payment method you set up. You don't need to do anything.</p>
<p>Want to change or cancel your plan before then? You can do it anytime from the Billing page.</p>
<p>{button}</p>
HTML,
    ],

    'usage_80' => [
        'label' => 'Usage warning (80%)',
        'sent_when' => 'A user has used 80% of their monthly messages.',
        'icon' => 'bi-speedometer2',
        'placeholders' => [
            'plan_name' => ['Their current plan', 'Free'],
            'percent' => ['Percent of the limit used', '80'],
            'used' => ['Messages sent this month', '400'],
            'limit' => ['Monthly message limit', '500'],
            'reset_date' => ['When the limit resets', 'November 1, 2026'],
        ],
        'subject' => "You've used {percent}% of your monthly messages",
        'button_text' => 'View plans',
        'body' => <<<'HTML'
<p>Hi {name},</p>
<p>You've sent {used} of {limit} messages included in your {plan_name} plan this month ({percent}%).</p>
<p>When you reach the limit, new messages will be refused until it resets on {reset_date}.</p>
<p>If you expect to send more, upgrade now so nothing stops unexpectedly.</p>
<p>{button}</p>
HTML,
    ],

    'usage_100' => [
        'label' => 'Usage limit reached (100%)',
        'sent_when' => 'A user has used all of their monthly messages.',
        'icon' => 'bi-exclamation-octagon',
        'placeholders' => [
            'plan_name' => ['Their current plan', 'Free'],
            'used' => ['Messages sent this month', '500'],
            'limit' => ['Monthly message limit', '500'],
            'reset_date' => ['When the limit resets', 'November 1, 2026'],
        ],
        'subject' => "You've reached your monthly message limit",
        'button_text' => 'Upgrade plan',
        'body' => <<<'HTML'
<p>Hi {name},</p>
<p>You've used all {used} of {limit} messages included in your {plan_name} plan this month.</p>
<p>New messages sent through the API will be refused until your limit resets on {reset_date}.</p>
<p>Upgrade your plan to keep sending right away — the new limit applies immediately.</p>
<p>{button}</p>
HTML,
    ],

    'instance_disconnected' => [
        'label' => 'Instance disconnected',
        'sent_when' => 'A WhatsApp instance goes offline and does not reconnect by itself.',
        'icon' => 'bi-wifi-off',
        'placeholders' => [
            'instance_name' => ['The instance name', 'Support'],
            'phone_number' => ['The linked number, or "no number"', '919876543210'],
            'disconnect_reason' => ['Why it disconnected', 'The device was logged out from the phone.'],
            'reconnect_hint' => ['Whether a new QR scan is needed', 'The device was unlinked, so you will need to scan a new QR code.'],
        ],
        'subject' => 'WhatsApp instance "{instance_name}" is offline',
        'button_text' => 'Open instance',
        'body' => <<<'HTML'
<p>Hi {name},</p>
<p>Your WhatsApp instance <strong>"{instance_name}"</strong> ({phone_number}) is no longer connected, so messages sent through the API will fail until it is back online.</p>
<p><strong>Reason:</strong> {disconnect_reason}</p>
<p>{reconnect_hint}</p>
<p>{button}</p>
HTML,
    ],

    'chatbot_handoff' => [
        'label' => 'Customer wants a person',
        'sent_when' => 'A customer replies "0" (talk to a person) to the chatbot menu.',
        'icon' => 'bi-person-raised-hand',
        'placeholders' => [
            'customer_phone' => ['The customer\'s WhatsApp number', '+919876543210'],
            'instance_name' => ['The instance they wrote to', 'Support'],
            'pause_time' => ['How long the bot stays quiet in that chat', '1 hour'],
        ],
        'subject' => 'A customer wants to talk to you on WhatsApp ({customer_phone})',
        'button_text' => 'Open messages',
        'body' => <<<'HTML'
<p>Hi {name},</p>
<p>A customer on <strong>"{instance_name}"</strong> chose <strong>"Talk to a person"</strong> in your chatbot menu and is waiting for a reply.</p>
<blockquote>Customer: <strong>{customer_phone}</strong></blockquote>
<p>Reply to them from your phone (or WhatsApp Web). The chatbot stays quiet in that chat for {pause_time}, so it won't talk over you.</p>
<p>{button}</p>
HTML,
    ],

];
