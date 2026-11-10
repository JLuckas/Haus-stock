{{-- KPI tile (a link). Optional: icon (an <x-icon> name), hint (small line under the value), alert (red emphasis). --}}
@props(['label', 'value', 'href' => null, 'alert' => false, 'icon' => null, 'hint' => null])

<a href="{{ $href ?? '#' }}" {{ $attributes->class([
    'surface group block min-w-0 p-4 transition hover:border-gray-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:hover:border-gray-700 sm:p-5',
    '!border-red-200 dark:!border-red-500/40' => $alert,
]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="eyebrow truncate">{{ $label }}</p>
        @if ($icon)
            <span @class([
                'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition',
                'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' => $alert,
                'bg-gray-100 text-gray-500 group-hover:bg-indigo-50 group-hover:text-indigo-600 dark:bg-gray-800 dark:text-gray-400 dark:group-hover:bg-indigo-500/10 dark:group-hover:text-indigo-300' => ! $alert,
            ])><x-icon :name="$icon" class="h-4 w-4" /></span>
        @endif
    </div>
    <p @class(['truncate text-2xl font-semibold tabular-nums tracking-tight', 'mt-1' => ! $icon, '-mt-1' => $icon, 'text-red-600 dark:text-red-400' => $alert, 'text-gray-900 dark:text-white' => ! $alert])>{{ $value }}</p>
    @if ($hint)<p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>@endif
</a>
