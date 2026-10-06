{{-- Next steps + links to the other guides, at the end of every guide. Expects $others. --}}
<h2 class="h4 fw-bold mt-5 mb-3">Next steps</h2>
<ul class="mb-4">
    <li class="mb-1">See every field, error and limit in the <a href="{{ route('docs.index') }}">API documentation</a>.</li>
    @unless (request()->routeIs('guides.show') && request()->route('slug') === 'receive-whatsapp-messages-webhook')
        <li class="mb-1"><a href="{{ route('guides.show', 'receive-whatsapp-messages-webhook') }}">Receive incoming messages with a webhook</a>, and get delivery updates on your server.</li>
    @endunless
    <li>Compare <a href="{{ route('pricing') }}">plans and message limits</a>.</li>
</ul>

@if (! empty($others))
    <h2 class="h5 fw-bold mb-3">More guides</h2>
    <div class="row g-3">
        @foreach ($others as $slug => $other)
            <div class="col-md-6">
                <a href="{{ route('guides.show', $slug) }}" class="card h-100 text-decoration-none shadow-sm border-0 gd-card">
                    <div class="card-body d-flex gap-3 align-items-start">
                        <i class="bi {{ $other['icon'] }} fs-3 text-primary"></i>
                        <div>
                            <div class="fw-semibold text-body">{{ $other['title'] }}</div>
                            <div class="small text-muted">{{ $other['language'] }} tutorial</div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endif
