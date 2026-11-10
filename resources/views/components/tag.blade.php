{{-- A tag chip. Colour is stored on the tag (assigned once from its name), so it never changes. --}}
@props(['tag', 'href' => null])

@php
    $palette = [
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/30',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-400/30',
        'pink' => 'bg-pink-50 text-pink-700 ring-pink-600/20 dark:bg-pink-500/10 dark:text-pink-300 dark:ring-pink-400/30',
        'teal' => 'bg-teal-50 text-teal-700 ring-teal-600/20 dark:bg-teal-500/10 dark:text-teal-300 dark:ring-teal-400/30',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30',
        'cyan' => 'bg-cyan-50 text-cyan-700 ring-cyan-600/20 dark:bg-cyan-500/10 dark:text-cyan-300 dark:ring-cyan-400/30',
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-400/20',
    ];
    $classes = 'inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($palette[$tag->color] ?? $palette['gray']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes.' hover:opacity-80') }}>#{{ $tag->name }}</a>
@else
    <span {{ $attributes->class($classes) }}>#{{ $tag->name }}</span>
@endif
