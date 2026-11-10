{{-- Filter chip (link). Use inside <nav class="flex flex-wrap gap-2">. --}}
@props(['href', 'active' => false, 'count' => null])

<a href="{{ $href }}" @if ($active) aria-current="true" @endif {{ $attributes->class([
    'inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-sm font-medium transition',
    'border-gray-900 bg-gray-900 text-white shadow-xs dark:border-white dark:bg-white dark:text-gray-900' => $active,
    'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-700 dark:hover:bg-gray-800/60' => ! $active,
]) }}>
    {{ $slot }}
    @if ($count !== null)<span @class(['rounded-full px-1.5 text-xs tabular-nums', 'bg-white/20 dark:bg-gray-900/10' => $active, 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => ! $active])>{{ $count }}</span>@endif
</a>
