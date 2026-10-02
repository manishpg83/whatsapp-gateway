{{-- Name + message fields, shared by the new and edit forms. $template is null when new. --}}
<div class="mb-3">
    <label for="tpl-name" class="form-label fw-semibold">Name</label>
    <input type="text" id="tpl-name" name="name" maxlength="100" required
           value="{{ old('name', $template?->name) }}" placeholder="e.g. Diwali greeting"
           class="form-control @error('name') is-invalid @enderror">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div>
    <label for="tpl-body" class="form-label fw-semibold">Message</label>
    <textarea id="tpl-body" name="body" rows="7" maxlength="4096" required
              placeholder="Happy Diwali, {name}! 🪔 Wishing you and your family a bright year ahead."
              class="form-control @error('body') is-invalid @enderror">{{ old('body', $template?->body) }}</textarea>
    @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text"><code>{name}</code> becomes each person's name. Works for text messages and media captions.</div>
</div>
