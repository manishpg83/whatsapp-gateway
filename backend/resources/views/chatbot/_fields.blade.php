{{-- Question / keywords / answer fields, shared by the new and edit forms. $rule is null when new.
     $prefill (optional) is a customer's question picked from "Unanswered questions". --}}
<div class="mb-3">
    <label for="cb-question" class="form-label fw-semibold">Question</label>
    <input type="text" id="cb-question" name="question" maxlength="150" required
           value="{{ old('question', $rule?->question ?? ($prefill ?? '')) }}" placeholder="e.g. What are your prices?"
           class="form-control @error('question') is-invalid @enderror">
    @error('question') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">A label for you — customers never see it.</div>
</div>
<div class="mb-3">
    <label for="cb-keywords" class="form-label fw-semibold">Keywords</label>
    <input type="text" id="cb-keywords" name="keywords" maxlength="1000" required @if (! empty($prefill)) autofocus @endif
           value="{{ old('keywords', $rule ? implode(', ', $rule->keywords) : '') }}" placeholder="price, cost, rate"
           class="form-control @error('keywords') is-invalid @enderror">
    @error('keywords') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">Separate with commas. Capital letters don't matter, "shoe" also matches "shoes", and whole words only — "hi" won't match "this".</div>
</div>
<div>
    <label for="cb-answer" class="form-label fw-semibold">Answer</label>
    <textarea id="cb-answer" name="answer" rows="6" maxlength="4096" required
              placeholder="Our plans start at ₹499/month. See all prices at example.com/pricing"
              class="form-control @error('answer') is-invalid @enderror">{{ old('answer', $rule?->answer) }}</textarea>
    @error('answer') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">Sent to the customer when their message has one of the keywords. With a file attached, this is its caption.</div>
</div>
<div class="mt-3">
    <label for="cb-media" class="form-label fw-semibold">Attach a file <span class="fw-normal text-muted">(optional)</span></label>

    @if ($rule?->hasMedia())
        <div class="d-flex flex-wrap align-items-center gap-2 small mb-2">
            <i class="bi {{ \App\Models\ChatbotRule::MEDIA_TYPES[$rule->media_type][1] }} text-primary"></i>
            <a href="{{ route('chatbot.rules.media', [$rule->whatsappSession->instance_id, $rule->id]) }}" target="_blank" rel="noopener" class="text-break">{{ $rule->mediaLabel() }}</a>
            <div class="form-check mb-0 ms-sm-2">
                <input type="checkbox" class="form-check-input" id="cb-remove-media" name="remove_media" value="1" @checked(old('remove_media'))>
                <label class="form-check-label" for="cb-remove-media">Remove</label>
            </div>
        </div>
    @endif

    <input type="file" id="cb-media" name="media" class="form-control @error('media') is-invalid @enderror"
           accept="image/jpeg,image/png,image/webp,video/mp4,video/3gpp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip">
    @error('media') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">
        @if ($rule?->hasMedia())
            Choose a new file to replace this one.
        @endif
        A photo (JPG/PNG/WEBP, max 5 MB), video (MP4, max 16 MB) or document such as a PDF price list (max 100 MB).
    </div>
</div>
