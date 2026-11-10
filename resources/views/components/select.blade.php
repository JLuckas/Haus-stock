@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'field-control pr-9']) }}>
    {{ $slot }}
</select>
