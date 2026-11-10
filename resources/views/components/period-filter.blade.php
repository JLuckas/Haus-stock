{{-- Period chips + custom date range. $period: App\Support\Period; $params: route parameters (e.g. ['kind' => …]); $query: extra query parameters to keep (e.g. a tag filter). --}}
@props(['period', 'route', 'params' => [], 'query' => []])

<div {{ $attributes->class('flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between') }} x-data="{ custom: @js($period->key === 'custom') }">
    <nav class="flex flex-wrap gap-2" aria-label="{{ __('Period') }}">
        @foreach (\App\Support\Period::presetLabels() as $key => $label)
            <x-pill :href="route($route, $params + ['period' => $key] + $query)" :active="$period->key === $key">{{ $label }}</x-pill>
        @endforeach
        <button type="button" x-on:click="custom = ! custom" @class([
            'inline-flex items-center rounded-full px-3 py-1.5 text-sm font-medium transition',
            'bg-gray-800 text-white dark:bg-gray-100 dark:text-gray-900' => $period->key === 'custom',
            'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-800 dark:hover:bg-gray-800/60' => $period->key !== 'custom',
        ])>{{ __('Custom') }}</button>
    </nav>

    <form method="GET" action="{{ route($route, $params) }}" x-show="custom" x-cloak class="flex flex-wrap items-end gap-2">
        <input type="hidden" name="period" value="custom">
        @foreach ($query as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <x-field :label="__('From')" for="period-from">
            <x-text-input id="period-from" name="from" type="date" class="block" :value="$period->from->toDateString()" required />
        </x-field>
        <x-field :label="__('To')" for="period-to">
            <x-text-input id="period-to" name="to" type="date" class="block" :value="$period->to->toDateString()" required />
        </x-field>
        <x-primary-button>{{ __('Apply') }}</x-primary-button>
    </form>
</div>
@if ($errors->has('from') || $errors->has('to') || $errors->has('period'))
    <x-input-error :messages="$errors->all()" />
@endif
