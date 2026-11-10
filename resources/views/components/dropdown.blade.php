@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'p-1'])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:close.stop="open = false" x-on:keydown.escape="open = false">
    <div x-on:click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-2 {{ $width }} max-w-[calc(100vw-2rem)] {{ $alignmentClasses }}"
            style="display: none;"
            role="menu"
            x-on:click="open = false">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-pop dark:border-gray-800 dark:bg-gray-900 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
