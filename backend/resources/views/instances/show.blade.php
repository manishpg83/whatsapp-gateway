@extends('layouts.app')

@section('title', $instance->name)

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">{{ $instance->name }}</h1>
            <a href="{{ route('instances.index') }}" class="btn btn-sm btn-outline-secondary">Back to instances</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body text-center py-5" id="instance-status" data-instance-status="{{ $instance->status }}">

                @if ($instance->status === 'connected')
                    <div class="text-success mb-3">
                        <div class="display-6">✓ Connected</div>
                    </div>
                    <p class="text-muted">{{ $instance->phone_number }}</p>

                    <form method="POST" action="{{ route('instances.destroy', $instance) }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">Disconnect</button>
                    </form>
                @elseif (in_array($instance->status, ['disconnected', 'logged_out']))
                    <div class="text-muted mb-3">
                        <div class="display-6">Not connected</div>
                        @if ($instance->last_disconnect_reason)
                            <p class="small mt-2">{{ $instance->last_disconnect_reason }}</p>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('instances.reconnect', $instance) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Reconnect</button>
                    </form>
                @else
                    {{-- connecting / qr_pending --}}
                    <p class="text-muted mb-3">Scan this QR code with WhatsApp on your phone:<br>
                        <small>Settings &rarr; Linked Devices &rarr; Link a Device</small>
                    </p>

                    <div id="qr-holder">
                        @if ($instance->qr_code)
                            <img src="{{ $instance->qr_code }}" alt="WhatsApp QR code" id="qr-image" class="img-fluid" style="max-width: 280px;">
                        @else
                            <p class="text-muted">Waiting for QR code&hellip;</p>
                        @endif
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const statusBox = document.getElementById('instance-status');
    let currentStatus = statusBox.dataset.instanceStatus;

    // Only the connecting/qr_pending states change on their own — once
    // connected/disconnected, the page's Disconnect/Reconnect buttons take
    // over, so there's nothing left to poll for.
    if (currentStatus !== 'connecting' && currentStatus !== 'qr_pending') {
        return;
    }

    const statusUrl = @json(route('instances.status', $instance));

    const poll = setInterval(async () => {
        let data;
        try {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            data = await response.json();
        } catch (e) {
            return; // network hiccup — just try again next tick
        }

        if (data.status !== currentStatus) {
            // Status moved to a terminal state (connected/disconnected/
            // logged_out) — reload so the page renders the right buttons.
            clearInterval(poll);
            window.location.reload();
            return;
        }

        if (data.qr_code) {
            const holder = document.getElementById('qr-holder');
            holder.innerHTML = '<img src="' + data.qr_code + '" alt="WhatsApp QR code" id="qr-image" class="img-fluid" style="max-width: 280px;">';
        }
    }, 2500);
})();
</script>
@endsection
