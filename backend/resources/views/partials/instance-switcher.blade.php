{{-- Which instance a page is showing — big buttons (a dropdown above 6
     instances), shown only when the user has more than one. Styles: cb-switch*
     in chatbot.css.

     Needs: $instances, $selected, $route (the page's route name; gets
     ?instance=), $label (the line above the buttons), $badge (on the current
     one, e.g. "Editing"). Optional: $meta, fn (WhatsappSession) => string,
     added after the phone number. --}}
@php $meta ??= null; @endphp
@if ($instances->count() > 1)
    <div class="cb-switcher mb-4 db-in" style="--i: 1;">
        <div class="cb-switcher-label">
            <i class="bi bi-phone"></i>
            <span>{{ $label }}</span>
        </div>
        @if ($instances->count() <= 6)
            <nav class="cb-switcher-list" aria-label="Instances">
                @foreach ($instances as $instance)
                    @php $current = $instance->is($selected); @endphp
                    <a href="{{ route($route, ['instance' => $instance->instance_id]) }}"
                       class="cb-switch {{ $current ? 'active' : '' }}" @if ($current) aria-current="page" @endif>
                        <span class="cb-switch-dot {{ $instance->status === 'connected' ? 'is-connected' : '' }}"
                              title="{{ $instance->status === 'connected' ? 'Connected' : 'Not connected' }}"></span>
                        <span class="cb-switch-text">
                            <span class="cb-switch-name">{{ $instance->name }}</span>
                            <span class="cb-switch-meta">
                                {{ $instance->phone_number ? '+'.$instance->phone_number : 'No number yet' }}@if ($meta) · {{ $meta($instance) }}@endif
                            </span>
                        </span>
                        @if ($current)
                            <span class="cb-switch-check"><i class="bi bi-check-lg"></i>{{ $badge }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
        @else
            <form method="GET" action="{{ route($route) }}" class="cb-switcher-select">
                <label for="cb-instance" class="visually-hidden">Instance</label>
                <select id="cb-instance" name="instance" class="form-select" onchange="this.form.submit()">
                    @foreach ($instances as $instance)
                        <option value="{{ $instance->instance_id }}" @selected($instance->is($selected))>
                            {{ $instance->name }}{{ $instance->phone_number ? ' (+'.$instance->phone_number.')' : '' }}@if ($meta) · {{ $meta($instance) }}@endif
                        </option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-outline-primary">Show</button></noscript>
            </form>
        @endif
    </div>
@endif
