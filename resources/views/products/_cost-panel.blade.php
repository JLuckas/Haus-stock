{{--
    Live "Cost and price" panel for the product form (state: productForm in resources/js/product-form.js).
    Server-rendered values ($metrics, saved recipe) show until Alpine takes over, then everything follows the form.
--}}
@php
    $money = fn ($v) => \App\Support\Money::format($v);
    $percent = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.').' %';
    $initial = $metrics ?? null;
    $statusBadge = [
        'ok' => ['Healthy margin', 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300'],
        'below' => ['Below target', 'bg-yellow-100 text-amber-800 dark:bg-yellow-400/15 dark:text-amber-300'],
        'loss' => ['Loss', 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300'],
        'no-price' => ['No price', 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'],
    ];
@endphp

<section class="surface overflow-hidden" aria-labelledby="cost-panel-title">
    <header class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
        <div>
            <h3 id="cost-panel-title" class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Cost and price') }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Updates as you edit the recipe') }}</p>
        </div>
        @foreach ($statusBadge as $key => [$label, $classes])
            <span x-show="status === @js($key)" x-cloak class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $classes }}">{{ __($label) }}</span>
        @endforeach
    </header>

    {{-- Recipe lines with their cost --}}
    <div class="px-4 py-4 sm:px-5">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Recipe cost') }}</p>

        <ul class="mt-2 space-y-1.5 text-sm" x-show="hasLines">
            <template x-for="row in lines" :key="row.key">
                <li class="flex items-baseline justify-between gap-3">
                    <span class="min-w-0 truncate text-gray-700 dark:text-gray-300" x-text="row.label"></span>
                    <span class="flex shrink-0 items-baseline gap-1 tabular-nums">
                        <template x-if="row.note === 'unmapped'"><span class="text-xs text-red-600 dark:text-red-400">{{ __('no recipe') }}</span></template>
                        <template x-if="row.note !== 'unmapped' && row.cost === null"><span class="text-xs text-yellow-700 dark:text-yellow-400">{{ __('no cost') }}</span></template>
                        <template x-if="row.cost !== null"><span class="text-gray-900 dark:text-gray-100" x-text="money(row.cost) + (row.note === 'partial' ? '+' : '')"></span></template>
                    </span>
                </li>
            </template>
        </ul>
        <p x-show="! hasLines" @if ($initial) x-cloak @endif class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Add ingredients or products to see what one unit costs.') }}</p>

        <div class="mt-3 flex items-baseline justify-between gap-3 border-t border-dashed border-gray-200 pt-3 dark:border-gray-700">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Total cost') }}</span>
            <span class="text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                <span x-text="money(totalCost)">{{ $initial ? $money($initial->cost) : 'R$ 0,00' }}</span><span x-show="missingCosts.length" @if (! $initial?->missingCostItems) x-cloak @endif class="text-yellow-600 dark:text-yellow-400" title="{{ __('Some ingredients have no cost') }}">+</span>
            </span>
        </div>

        <p x-show="missingCosts.length" x-cloak class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-400/10 dark:text-amber-300">
            {{ __('No cost set for') }} <span class="font-medium" x-text="missingCosts.join(', ')"></span>{{ __(', so the real cost is higher.') }}
            <a href="{{ route('inventory.index') }}" class="font-medium underline">{{ __('Set costs') }}</a>
        </p>
        <p x-show="hasUnmapped" x-cloak class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-500/10 dark:text-red-300">
            {{ __('A product inside has no recipe yet, so its cost is unknown.') }}
        </p>
    </div>

    {{-- Target margin → recommended price --}}
    <div class="border-t border-gray-100 bg-gray-50/70 px-4 py-4 dark:border-gray-800 dark:bg-gray-800/30 sm:px-5">
        <div class="flex items-end justify-between gap-4">
            <div class="min-w-0">
                <label for="target_margin_input" class="block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Target margin') }}</label>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400" x-show="usesDefaultTarget">{{ __('Store default') }}</p>
                <button type="button" x-show="! usesDefaultTarget" x-cloak x-on:click="targetMargin = ''" class="mt-0.5 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ __('Use the store default') }}</button>
            </div>
            <div class="flex w-28 shrink-0 items-stretch">
                <x-decimal-input id="target_margin_input" decimals="1" x-model="targetMargin" class="block w-full min-w-0 rounded-r-none text-right" :placeholder="number_format($defaultMargin, 0, ',', '.')" :aria-label="__('Target margin (%)')" />
                <span class="inline-flex w-9 shrink-0 items-center justify-center rounded-r-lg border border-l-0 border-gray-300 bg-white text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">%</span>
            </div>
        </div>

        <div class="mt-4 rounded-lg bg-white p-3 ring-1 ring-indigo-100 dark:bg-gray-900 dark:ring-indigo-500/30">
            <p class="text-xs font-medium uppercase tracking-wide text-indigo-700 dark:text-indigo-300">{{ __('Recommended price') }}</p>
            <p class="mt-0.5 text-3xl font-bold tabular-nums text-gray-900 dark:text-gray-50" x-text="money(recommendedPrice)">{{ $initial ? $money($initial->recommendedPrice) : '—' }}</p>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                {{ __('Cost ÷ (1 − margin), so') }} <span x-text="percent(target)">{{ $percent($initial?->targetMarginPercent ?? $defaultMargin) }}</span> {{ __('of the price is left after the cost.') }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2" x-show="recommendedPrice !== null" @if (! $initial?->recommendedPrice) x-cloak @endif>
                <button type="button" x-on:click="usePrice(recommendedPrice)"
                        class="btn btn-primary h-8 px-3 text-xs">
                    {{ __('Use') }}&nbsp;<span x-text="money(recommendedPrice)"></span>
                </button>
                <button type="button" x-on:click="usePrice(roundedPrice)" x-show="roundedPrice !== recommendedPrice"
                        class="btn btn-secondary h-8 px-3 text-xs">
                    {{ __('Round to') }}&nbsp;<span x-text="money(roundedPrice)"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- How the current price performs --}}
    <dl class="space-y-1.5 border-t border-gray-100 px-4 py-4 text-sm dark:border-gray-800 sm:px-5">
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Current price') }}</dt>
            <dd class="font-medium tabular-nums text-gray-900 dark:text-gray-100" x-text="money(priceValue)">{{ $money($product->price ?? 0) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400" x-text="profit < 0 ? @js(__('Loss')) : @js(__('Profit'))">{{ __('Profit') }}</dt>
            <dd class="font-semibold tabular-nums" :class="profit < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'" x-text="money(profit)">{{ $initial ? $money($initial->profit) : '—' }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Margin') }}</dt>
            <dd class="font-medium tabular-nums text-gray-900 dark:text-gray-100" x-text="percent(marginPercent)">{{ $percent($initial?->marginPercent) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Food cost (CMV)') }}</dt>
            <dd class="tabular-nums text-gray-700 dark:text-gray-300" x-text="percent(foodCostPercent)">{{ $percent($initial?->foodCostPercent) }}</dd>
        </div>

        {{-- Margin bar: fill = current margin, tick = target --}}
        <div class="pt-2" x-show="status !== 'empty' && status !== 'no-price'" @if (! $initial || $initial->marginPercent === null) x-cloak @endif>
            <div class="relative h-2 rounded-full bg-gray-100 dark:bg-gray-800" role="img"
                 :aria-label="@js(__('Margin')) + ' ' + percent(marginPercent) + ', ' + @js(__('Target margin')) + ' ' + percent(target)">
                <div class="h-2 rounded-full transition-all duration-300"
                     :class="{ 'bg-green-500': status === 'ok', 'bg-yellow-400': status === 'below', 'bg-red-500': status === 'loss' }"
                     :style="`width: ${marginBar}%`"></div>
                <div class="absolute -top-1 h-4 w-0.5 rounded bg-gray-700 dark:bg-gray-200" :style="`left: calc(${targetMarker}% - 1px)`"></div>
            </div>
            <div class="mt-1 flex justify-between text-[11px] text-gray-500 dark:text-gray-400">
                <span>0 %</span>
                <span>{{ __('target') }} <span x-text="percent(target)"></span></span>
                <span>100 %</span>
            </div>
        </div>
    </dl>
</section>

{{-- Phones and tablets: the panel sits below the form, so keep the key numbers in view while editing. --}}
<div class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-4 py-2.5 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 lg:hidden"
     x-show="hasLines" x-cloak>
    <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 text-sm">
        <div class="min-w-0">
            <p class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Total cost') }}</p>
            <p class="font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="money(totalCost) + (missingCosts.length ? '+' : '')"></p>
        </div>
        <div class="min-w-0 text-center">
            <p class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Margin') }}</p>
            <p class="font-semibold tabular-nums" :class="{ 'text-green-700 dark:text-green-400': status === 'ok', 'text-yellow-700 dark:text-yellow-400': status === 'below', 'text-red-600 dark:text-red-400': status === 'loss', 'text-gray-500': status === 'no-price' }" x-text="percent(marginPercent)"></p>
        </div>
        <button type="button" class="min-w-0 rounded-md bg-indigo-50 px-3 py-1.5 text-right ring-1 ring-indigo-100 dark:bg-indigo-500/10 dark:ring-indigo-500/30"
                x-on:click="usePrice(recommendedPrice)" :disabled="recommendedPrice === null" :title="@js(__('Use the recommended price'))">
            <p class="text-[11px] uppercase tracking-wide text-indigo-700 dark:text-indigo-300">{{ __('Recommended') }}</p>
            <p class="font-semibold tabular-nums text-indigo-900 dark:text-indigo-100" x-text="money(recommendedPrice)"></p>
        </button>
    </div>
</div>
