{{-- A message's status badge, the same everywhere: Pending, Sent ✓,
     Delivered ✓✓, Read ✓✓ (blue), Failed, Received. See Message::statusBadge(). --}}
@props(['message'])

@php([$color, $label, $icon] = $message->statusBadge())

<span {{ $attributes->merge(['class' => "badge rounded-pill bg-{$color}-subtle text-{$color}-emphasis border border-{$color}-subtle"]) }}
      @if ($message->read_at) title="Read {{ $message->read_at->format('Y-m-d H:i') }}"
      @elseif ($message->delivered_at) title="Delivered {{ $message->delivered_at->format('Y-m-d H:i') }}" @endif>
    <i class="bi {{ $icon }}"></i> {{ $label }}
</span>
{{-- Plus the Cloud API fallback result, when one was attempted. --}}
@if ($fallback = $message->fallbackBadge())
    @php([$fbColor, $fbLabel, $fbIcon] = $fallback)
    <span class="badge rounded-pill bg-{{ $fbColor }}-subtle text-{{ $fbColor }}-emphasis border border-{{ $fbColor }}-subtle"
          @if ($message->fallback_error) title="{{ \Illuminate\Support\Str::limit($message->fallback_error, 200) }}" @endif>
        <i class="bi {{ $fbIcon }}"></i> {{ $fbLabel }}
    </span>
@endif
