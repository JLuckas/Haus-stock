{{-- A link that looks like a button. variant: primary | secondary | ghost | danger; icon: an <x-icon> name. --}}
@props(['variant' => 'primary', 'icon' => null])

<a {{ $attributes->merge(['class' => 'btn btn-'.(in_array($variant, ['primary', 'secondary', 'ghost', 'danger'], true) ? $variant : 'primary')]) }}>
    @if ($icon)<x-icon :name="$icon" class="h-4 w-4 opacity-80" />@endif
    {{ $slot }}
</a>
