{{-- Label + control + hint + validation error, the shape every form field repeats. --}}
@props(['label' => null, 'for' => null, 'hint' => null, 'error' => null])

<div {{ $attributes }}>
    @if ($label)<x-input-label :for="$for" :value="$label" />@endif
    <div @class(['mt-1' => $label])>{{ $slot }}</div>
    @if ($hint)<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>@endif
    @if ($error)<x-input-error :messages="$errors->get($error)" class="mt-2" />@endif
</div>
