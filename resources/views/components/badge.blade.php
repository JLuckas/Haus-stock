{{-- Status label. color: gray | green | red | yellow | amber | blue | indigo; dot: leading status dot. --}}
@props(['color' => 'gray', 'dot' => false])

@php
    // Full class names so Tailwind's scanner keeps them in the build.
    $colors = [
        'gray' => ['bg-gray-100 text-gray-700 ring-gray-500/15 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-400/20', 'bg-gray-400'],
        'green' => ['bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-400/25', 'bg-green-500'],
        'red' => ['bg-red-50 text-red-700 ring-red-600/15 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-400/25', 'bg-red-500'],
        'yellow' => ['bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/25', 'bg-amber-500'],
        'blue' => ['bg-blue-50 text-blue-700 ring-blue-600/15 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/25', 'bg-blue-500'],
        'indigo' => ['bg-indigo-50 text-indigo-700 ring-indigo-600/15 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/25', 'bg-indigo-500'],
    ];
    $colors['amber'] = $colors['yellow'];
    [$classes, $dotClass] = $colors[$color] ?? $colors['gray'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.$classes]) }}>
    @if ($dot)<span class="h-1.5 w-1.5 rounded-full {{ $dotClass }}" aria-hidden="true"></span>@endif
    {{ $slot }}
</span>
