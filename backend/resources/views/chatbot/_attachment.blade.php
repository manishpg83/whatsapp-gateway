{{-- The file sent with an entry's answer, if it has one. Needs $rule and $selected. --}}
@if ($rule->hasMedia())
    <a href="{{ route('chatbot.rules.media', [$selected->instance_id, $rule->id]) }}" target="_blank" rel="noopener"
       class="d-inline-flex align-items-center gap-1 small text-break">
        <i class="bi {{ \App\Models\ChatbotRule::MEDIA_TYPES[$rule->media_type][1] }}"></i>
        <span>{{ $rule->mediaLabel() }}</span>
        <span class="text-muted">· {{ \App\Models\ChatbotRule::MEDIA_TYPES[$rule->media_type][0] }}</span>
    </a>
@endif
