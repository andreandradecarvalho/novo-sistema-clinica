@props(['name', 'label', 'type' => 'text', 'autocomplete' => null, 'value' => null])
<div class="mb-4">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" class="form-control" required
           @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @if($type !== 'password') value="{{ $value ?? old($name) }}" @endif>
</div>
