{{-- A password field with a show/hide "eye" button (see resources/js/app.js).
     Usage: <x-password-input id="password" name="password" autocomplete="new-password" />
     `error` is the validation field to show errors for (defaults to `name`);
     `bag` is the error bag, for pages with more than one form. --}}
@props([
    'id',
    'name',
    'autocomplete' => 'current-password',
    'error' => null,
    'bag' => 'default',
    'required' => true,
])

@php($errorField = $error ?? $name)

<div class="input-group has-validation">
    <input type="password" id="{{ $id }}" name="{{ $name }}" autocomplete="{{ $autocomplete }}"
           {{ $attributes->merge(['class' => 'form-control'.($errors->getBag($bag)->has($errorField) ? ' is-invalid' : '')]) }} @required($required)>
    <button type="button" class="btn btn-outline-secondary" data-password-toggle="#{{ $id }}"
            aria-label="Show password" title="Show password">
        <i class="bi bi-eye"></i>
    </button>
    @error($errorField, $bag)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
