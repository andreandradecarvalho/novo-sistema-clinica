@props(['name', 'label', 'type' => 'text', 'autocomplete' => null, 'value' => null, 'errorBag' => 'default', 'required' => true])
@php($fieldError = $errors->getBag($errorBag)->first($name))
<div class="mb-6">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    @if($type === 'password')<div class="input-group input-group-merge">@endif
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @class(['form-control', 'is-invalid' => $fieldError]) @required($required)
           @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @if($type !== 'password') value="{{ $value ?? old($name) }}" @endif
           @if($fieldError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
           {{ $attributes }}>
    @if($type === 'password')
        <button type="button" class="input-group-text password-toggle" data-password-toggle="{{ $name }}" aria-controls="{{ $name }}" aria-pressed="false" aria-label="Mostrar senha: {{ $label }}"><x-icon name="eye-off" /></button>
    </div>
    @endif
    @if($fieldError)<div id="{{ $name }}-error" class="invalid-feedback d-block">{{ $fieldError }}</div>@endif
</div>
