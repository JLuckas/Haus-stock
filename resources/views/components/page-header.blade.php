{{-- Page title row: title, optional subtitle, actions on the right (they wrap under the title on phones). --}}
@props(['title', 'subtitle' => null])

<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
