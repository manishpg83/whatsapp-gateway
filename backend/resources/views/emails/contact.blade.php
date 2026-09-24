{{-- Plain-text email: {!! !!} so apostrophes etc. aren't turned into HTML entities (nothing renders HTML here). --}}
New message from the {{ config('app.name') }} contact page.

Topic: {!! $topic !!}
From:  {!! $senderName !!} <{!! $senderEmail !!}>
Account: {!! $userId ? "registered user #{$userId}" : 'not logged in' !!}

----------------------------------------
{!! $messageText !!}
----------------------------------------

Reply to this email to answer them directly.
