{{-- Question / keywords / answer fields, shared by the new and edit forms. $rule is null when new. --}}
<div class="mb-3">
    <label for="cb-question" class="form-label fw-semibold">Question</label>
    <input type="text" id="cb-question" name="question" maxlength="150" required
           value="{{ old('question', $rule?->question) }}" placeholder="e.g. What are your prices?"
           class="form-control @error('question') is-invalid @enderror">
    @error('question') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">A label for you — customers never see it.</div>
</div>
<div class="mb-3">
    <label for="cb-keywords" class="form-label fw-semibold">Keywords</label>
    <input type="text" id="cb-keywords" name="keywords" maxlength="1000" required
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
    <div class="form-text">Sent to the customer when their message has one of the keywords.</div>
</div>
