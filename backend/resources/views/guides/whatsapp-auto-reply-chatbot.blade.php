@extends('guides._layout')

@php
    $plans = \App\Models\Plan::orderBy('price')->get();
    $pauseChoices = array_values(array_filter(\App\Models\ChatbotPause::DURATIONS, fn ($label, $minutes) => $minutes > 0, ARRAY_FILTER_USE_BOTH));
    $yes = '<i class="bi bi-check-circle-fill text-success" aria-label="Matches"></i>';
    $no = '<i class="bi bi-x-circle-fill text-danger" aria-label="Doesn\'t match"></i>';
@endphp

@section('guide_body')
<p>
    The {{ config('app.name') }} <strong>chatbot</strong> answers common WhatsApp questions for you &mdash; prices, opening
    hours, your address, delivery times &mdash; using keywords you choose. When a customer's message contains one of
    your keywords, they get your answer within seconds. Everything else is left for you to answer yourself.
</p>
<p class="mb-0">You set it up from your dashboard. <strong>No code is needed.</strong></p>

<h2 class="h4 fw-bold mt-5 mb-3" id="how-it-works">How it works</h2>
<ol>
    <li class="mb-2">A customer sends a WhatsApp message to your connected number.</li>
    <li class="mb-2">The chatbot looks for your keywords in the message.</li>
    <li class="mb-2">If it finds one, it sends that entry's answer automatically. The reply appears on your Messages page with a <strong>Bot</strong> label.</li>
    <li>If no keyword matches, the chatbot <strong>does nothing</strong> and the message waits for you, as usual.</li>
</ol>

<h2 class="h4 fw-bold mt-5 mb-3" id="entries">Step 1: Add your questions and answers</h2>
<p>
    Open <strong>Chatbot</strong> in your dashboard. If you have more than one WhatsApp number, pick it from the
    <strong>Instance</strong> list at the top &mdash; each number has its own chatbot. Then add an <strong>entry</strong>
    for each question customers often ask:
</p>
<ul>
    <li class="mb-1"><strong>Question</strong> &mdash; a label for you, like "What are your prices?". Customers never see it.</li>
    <li class="mb-1"><strong>Keywords</strong> &mdash; the words that trigger this answer, separated by commas.</li>
    <li><strong>Answer</strong> &mdash; the message the customer receives.</li>
</ul>
<p>For example, a shoe shop might add:</p>
<div class="table-responsive">
    <table class="table table-bordered align-middle small">
        <thead class="table-light">
            <tr><th>Question</th><th>Keywords</th><th>Answer</th></tr>
        </thead>
        <tbody>
            <tr><td>Greeting</td><td>hi, hello, hey</td><td>Welcome to ABC Shoes! Ask us about prices, timings or our address.</td></tr>
            <tr><td>Prices</td><td>price, cost, rate</td><td>Our sneakers start at {{ \App\Support\Currency::symbol() }}{{ \App\Support\Currency::isZar() ? '499' : '999' }}. See everything at example.com/shop.</td></tr>
            <tr><td>Timings</td><td>timing, open, close, hours</td><td>We're open 10 AM to 8 PM, Monday to Saturday.</td></tr>
            <tr><td>Location</td><td>address, location, where</td><td>Shop 12, MG Road. Here's the map: example.com/map</td></tr>
        </tbody>
    </table>
</div>

<h2 class="h4 fw-bold mt-5 mb-3" id="matching">How keywords are matched</h2>
<ul>
    <li class="mb-2"><strong>Capital letters don't matter.</strong> <code>price</code> matches "PRICE" and "Price".</li>
    <li class="mb-2"><strong>Whole words only.</strong> <code>hi</code> matches "hi there" but not "this" or "ship".</li>
    <li class="mb-2"><strong>Singular and plural count as the same word.</strong> <code>shoe</code> matches "shoes", <code>sneakers</code> matches "sneaker", <code>box</code> matches "boxes".</li>
    <li class="mb-2"><strong>Phrases work too.</strong> <code>opening time</code> matches "what is your opening time?" and "opening times".</li>
    <li><strong>Photos and videos count.</strong> The chatbot also reads the caption of a photo or video.</li>
</ul>
<div class="table-responsive">
    <table class="table table-bordered align-middle small text-center">
        <thead class="table-light">
            <tr><th class="text-start">Customer writes</th><th>Keyword</th><th>Match?</th></tr>
        </thead>
        <tbody>
            <tr><td class="text-start">What is the PRICE?</td><td><code>price</code></td><td>{!! $yes !!}</td></tr>
            <tr><td class="text-start">Do you have sneakers?</td><td><code>sneaker</code></td><td>{!! $yes !!}</td></tr>
            <tr><td class="text-start">this is great</td><td><code>hi</code></td><td>{!! $no !!}</td></tr>
            <tr><td class="text-start">priceless</td><td><code>price</code></td><td>{!! $no !!}</td></tr>
            <tr><td class="text-start">My order is late</td><td><code>price</code></td><td>{!! $no !!} &mdash; left for you</td></tr>
        </tbody>
    </table>
</div>
<p class="small text-muted">
    Plural matching follows simple English rules. Numbers, prices like <code>{{ \App\Support\Currency::symbol() }}499</code> and
    words in other languages must match exactly, so add every spelling customers might use.
</p>

<h3 class="h5 fw-bold mt-4" id="best-match">When more than one entry matches</h3>
<p>
    Only <strong>one</strong> answer is ever sent. The entry with the <strong>most matching keywords</strong> wins. If two
    entries match equally, the one <strong>higher in your list</strong> wins. New entries go to the bottom, and you can move
    any entry up or down with the arrow buttons. For example, with these two entries:
</p>
<ul>
    <li class="mb-1"><strong>Shoes</strong> &mdash; keywords <code>shoes, shoe, boot</code></li>
    <li><strong>Prices</strong> &mdash; keywords <code>shoe, sneakers, price, cost</code></li>
</ul>
<div class="table-responsive">
    <table class="table table-bordered align-middle small">
        <thead class="table-light">
            <tr><th>Customer writes</th><th>Shoes</th><th>Prices</th><th>Who answers</th></tr>
        </thead>
        <tbody>
            <tr><td>what is the price of these shoes?</td><td>1 (shoe)</td><td>2 (shoe, price)</td><td><strong>Prices</strong></td></tr>
            <tr><td>do you have shoes?</td><td>1</td><td>1</td><td><strong>Shoes</strong> (a tie: it's higher in the list)</td></tr>
            <tr><td>any boots?</td><td>1 (boot)</td><td>0</td><td><strong>Shoes</strong></td></tr>
        </tbody>
    </table>
</div>

<h2 class="h4 fw-bold mt-5 mb-3" id="test">Step 2: Test it before going live</h2>
<p class="mb-0">
    Use the <strong>Test bot</strong> box on the Chatbot page. Type a message as if you were a customer and it shows
    which entry would answer and which keywords it found &mdash; or that the bot would stay silent.
    <strong>Nothing is sent on WhatsApp</strong>, so you can try as many messages as you like.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="switch-on">Step 3: Switch it on</h2>
<p class="mb-0">
    Click <strong>Switch on</strong> in the <strong>Auto-reply</strong> card. The chatbot starts answering straight away,
    as long as the instance is connected. Click <strong>Switch off</strong> any time to stop all automatic replies.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="business-hours">Optional: business hours</h2>
<p>
    Turn on <strong>Business hours</strong> to tell people when you're closed. Choose your open days, opening and closing
    times and your timezone, and write a "we're closed" message. Outside those hours:
</p>
<ul>
    <li class="mb-2">Messages that match a keyword <strong>still get their answer</strong> &mdash; the chatbot works around the clock.</li>
    <li class="mb-2">Messages that match nothing get your <strong>"we're closed" message</strong>, at most once every {{ \App\Models\ChatbotRule::CLOSED_MESSAGE_WAIT_HOURS }} hours per person.</li>
    <li>Closing earlier than opening means overnight: open 20:00, close 02:00 covers the evening and past midnight.</li>
</ul>
<p class="mb-0">Inside your hours, messages that match nothing get no reply at all, so you can answer them yourself.</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="pause">When you reply yourself, the bot steps back</h2>
<p>
    If you answer a customer from your own phone (or WhatsApp Web), the chatbot goes quiet <strong>in that chat</strong>,
    so it never talks over you. Every customer you haven't replied to still gets automatic answers.
</p>
<ul class="mb-0">
    <li class="mb-1">The pause lasts 30 minutes by default. You can choose {{ implode(', ', array_slice($pauseChoices, 0, -1)) }} or {{ end($pauseChoices) }} &mdash; or switch pausing off.</li>
    <li class="mb-1">Each time you reply again, the pause starts over.</li>
    <li>Paused chats are listed on the Chatbot page, each with a <strong>Resume now</strong> button.</li>
</ul>

<h2 class="h4 fw-bold mt-5 mb-3" id="protections">Built-in protections</h2>
<p>The chatbot is careful not to annoy your customers or put your number at risk:</p>
<ul class="mb-0">
    <li class="mb-2">It only replies to <strong>one-to-one chats</strong> &mdash; never in groups, never to status updates, and never to your own messages.</li>
    <li class="mb-2">It won't send the <strong>same answer</strong> to the same person twice within {{ \App\Models\ChatbotRule::REPEAT_WAIT_MINUTES }} minutes.</li>
    <li class="mb-2">One person gets at most <strong>{{ \App\Models\ChatbotRule::MAX_REPLIES_PER_CONTACT_PER_HOUR }} automatic replies an hour</strong>, so two bots can never get stuck answering each other.</li>
    <li>It only replies when it knows the sender's real phone number. In the rare case WhatsApp hides it, the message is left for you.</li>
</ul>

<h2 class="h4 fw-bold mt-5 mb-3" id="stats">See what it's doing</h2>
<p class="mb-0">
    The Chatbot page shows how many answers and "closed" messages were sent in the last 7 days, and every entry shows how
    often it has replied. An entry marked <strong>Not used yet</strong> may need better keywords.
</p>

<h2 class="h4 fw-bold mt-5 mb-3" id="limits">Limits and plans</h2>
<ul>
    <li class="mb-1">Automatic replies are messages you send, so they <strong>count toward your monthly message limit</strong>. If the limit is reached, the chatbot stops replying until you upgrade or the new month starts.</li>
    <li class="mb-1">Each entry can have up to {{ \App\Models\ChatbotRule::MAX_KEYWORDS }} keywords of up to {{ \App\Models\ChatbotRule::MAX_KEYWORD_LENGTH }} characters, and each WhatsApp number up to {{ \App\Models\ChatbotRule::MAX_PER_INSTANCE }} entries.</li>
    <li>How many entries you can have in total depends on your plan:</li>
</ul>
<div class="table-responsive">
    <table class="table table-bordered align-middle small text-center">
        <thead class="table-light">
            <tr>
                <th class="text-start">Plan</th>
                @foreach ($plans as $plan)
                    <th>{{ $plan->name }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-start">Chatbot entries</td>
                @foreach ($plans as $plan)
                    <td>{{ $plan->chatbot_entries > 0 ? number_format($plan->chatbot_entries) : 'Not included' }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>
<p class="small"><a href="{{ route('pricing') }}">Compare all plans</a></p>

<h2 class="h4 fw-bold mt-5 mb-3" id="tips">Tips for a helpful chatbot</h2>
<ul class="mb-0">
    <li class="mb-2"><strong>Add the words customers really use.</strong> Look at your recent chats: "rate", "charges" and "how much" may all mean price.</li>
    <li class="mb-2"><strong>Avoid very common words</strong> like "ok", "yes" or "thanks" as keywords &mdash; they'd trigger answers nobody asked for.</li>
    <li class="mb-2"><strong>Keep answers short</strong> and end with what to do next: a link, a phone number, or "reply here and we'll help".</li>
    <li><strong>Check the stats every few weeks</strong> and improve entries that are never used.</li>
</ul>
@endsection
