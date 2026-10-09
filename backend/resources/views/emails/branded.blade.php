{{-- The branded layout every user email is sent in — built by
     App\Services\EmailTemplates. $bodyBefore / $bodyAfter are the template
     body (already cleaned, filled and styled) on each side of the button;
     $actionText / $actionUrl come from MailMessage::action().
     Table layout + inline styles only: many email apps ignore <style>. --}}
@php
    $font = "font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
    $logo = $message->embed(public_path('images/brand/instamessage-logo-email.png'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background-color:#eef3f1;{{ $font }}">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef3f1;">
    <tr>
        <td align="center" style="padding:32px 12px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">

                {{-- Logo --}}
                <tr>
                    <td align="center" style="padding:0 0 20px;">
                        <a href="{{ route('home') }}" style="text-decoration:none;">
                            <img src="{{ $logo }}" width="220" height="40" alt="{{ config('app.name') }}" style="display:block;width:220px;max-width:100%;height:auto;border:0;">
                        </a>
                    </td>
                </tr>

                {{-- Card --}}
                <tr>
                    <td style="background-color:#ffffff;border-radius:14px;border-top:4px solid #0b8457;padding:36px 36px 28px;{{ $font }}">
                        {!! $bodyBefore !!}

                        @if ($actionText && $actionUrl)
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
                                <tr>
                                    <td align="center" bgcolor="#0b8457" style="border-radius:8px;">
                                        <a href="{{ $actionUrl }}" target="_blank" style="display:inline-block;padding:13px 28px;font-size:15px;font-weight:700;line-height:1.2;color:#ffffff;text-decoration:none;border-radius:8px;{{ $font }}">{{ $actionText }}</a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        {!! $bodyAfter !!}

                        @if ($actionText && $actionUrl)
                            <p style="margin:24px 0 0;padding-top:18px;border-top:1px solid #e3ebe7;font-size:12px;line-height:1.6;color:#7a8a83;">
                                Button not working? Copy and paste this link into your browser:<br>
                                <a href="{{ $actionUrl }}" style="color:#0b8457;word-break:break-all;">{{ $actionUrl }}</a>
                            </p>
                        @endif
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding:24px 16px 0;font-size:12px;line-height:1.7;color:#7a8a83;{{ $font }}">
                        <a href="{{ route('dashboard') }}" style="color:#4a5a53;text-decoration:none;font-weight:600;">Dashboard</a>
                        &nbsp;·&nbsp;
                        <a href="{{ route('docs.index') }}" style="color:#4a5a53;text-decoration:none;font-weight:600;">API Docs</a>
                        &nbsp;·&nbsp;
                        <a href="{{ route('contact') }}" style="color:#4a5a53;text-decoration:none;font-weight:600;">Contact us</a>
                        <br>
                        <strong style="color:#4a5a53;">{{ config('app.name') }}</strong> — Your WhatsApp API, simplified.<br>
                        You're receiving this email because of your {{ config('app.name') }} account.<br>
                        &copy; {{ date('Y') }} {{ config('company.name') }}. All Rights Reserved.
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
