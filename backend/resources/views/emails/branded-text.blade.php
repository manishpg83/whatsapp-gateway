{{-- Plain-text part of the branded email (see emails/branded.blade.php).
     Raw output on purpose: this is not HTML, so nothing is interpreted. --}}
{!! $textBefore !!}

@if ($actionText && $actionUrl)
{!! $actionText !!}: {!! $actionUrl !!}

@endif
{!! $textAfter !!}

--
{!! config('app.name') !!} — Your WhatsApp API, simplified.
BriskBrain Technologies · {!! route('contact') !!}
