{{-- Shared fields for create/edit — the two views differ only in the
     surrounding <form> tag (action/method) and whether slug/subscriber
     info is shown. Inputs carry data-preview so _preview.blade.php can
     mirror them live. --}}
<div class="ad-form-section">
    <div class="ad-action-title"><i class="bi bi-card-text"></i> Basics</div>

    <div class="mb-3">
        <label for="name" class="form-label">Name</label>
        <input type="text" class="form-control @error('name') is-invalid @enderror"
               id="name" name="name" value="{{ old('name', $plan->name ?? '') }}" required autofocus
               data-preview="name">
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label for="description" class="form-label">Description</label>
        <input type="text" class="form-control @error('description') is-invalid @enderror"
               id="description" name="description" value="{{ old('description', $plan->description ?? '') }}"
               placeholder="A one-line tagline shown on the pricing card" required
               data-preview="description">
        @error('description')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="ad-form-section">
    <div class="ad-action-title"><i class="bi bi-sliders"></i> Price &amp; limits</div>

    <div class="row g-3">
        <div class="col-md-4">
            <label for="price" class="form-label">Price (INR/mo)</label>
            <div class="input-group has-validation">
                <span class="input-group-text">&#8377;</span>
                <input type="number" min="0" step="1" class="form-control @error('price') is-invalid @enderror"
                       id="price" name="price" value="{{ old('price', $plan->price ?? 0) }}" required
                       data-preview="price">
                @error('price')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-text">0 = free plan, no checkout involved.</div>
        </div>
        <div class="col-md-4">
            <label for="instances" class="form-label">Instance limit</label>
            <input type="number" min="1" step="1" class="form-control @error('instances') is-invalid @enderror"
                   id="instances" name="instances" value="{{ old('instances', $plan->instances ?? 1) }}" required
                   data-preview="instances">
            @error('instances')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-4">
            <label for="messages_per_month" class="form-label">Messages/month</label>
            <input type="number" min="1" step="1" class="form-control @error('messages_per_month') is-invalid @enderror"
                   id="messages_per_month" name="messages_per_month" value="{{ old('messages_per_month', $plan->messages_per_month ?? 50) }}" required
                   data-preview="messages">
            @error('messages_per_month')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="ad-form-section">
    <div class="ad-action-title"><i class="bi bi-star"></i> Visibility</div>

    <div class="form-check form-switch ad-switch mb-0">
        <input type="checkbox" class="form-check-input" role="switch" id="popular" name="popular" value="1"
               @checked(old('popular', $plan->popular ?? false)) data-preview="popular">
        <label class="form-check-label" for="popular">Highlight as "Most popular" on the pricing page</label>
    </div>
</div>
