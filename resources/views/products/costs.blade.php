<x-app-layout>
    @php
        $money = fn ($v) => \App\Support\Money::format($v);
        $percent = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.').' %';
        $show = $filters['show'] ?? null;
        $search = $filters['search'] ?? null;
        $keep = array_filter(['search' => $search]);
    @endphp

    <x-slot name="title">{{ __('Product costs') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Products')" :subtitle="__('What one unit of each product costs (every ingredient, including products inside it) and the price its target margin needs.')">
            <x-slot name="actions">
                <x-link-button :href="route('products.create')">{{ __('Add product') }}</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-product-tabs />

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {{-- Store target margin --}}
        <form method="POST" action="{{ route('products.target-margin') }}" class="surface p-4 sm:p-5 md:col-span-2 xl:col-span-1">
            @csrf
            @method('PUT')
            <label for="store_target_margin" class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Store target margin') }}</label>
            <div class="mt-1 flex items-stretch gap-2">
                <div class="flex w-28 items-stretch">
                    <x-decimal-input id="store_target_margin" name="target_margin_percent" decimals="1" :value="$targetMargin" class="block w-full min-w-0 rounded-r-none text-right text-lg font-semibold" required />
                    <span class="inline-flex w-9 shrink-0 items-center justify-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-50 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">%</span>
                </div>
                <x-primary-button>{{ __('Save') }}</x-primary-button>
            </div>
            <x-input-error :messages="$errors->get('target_margin_percent')" class="mt-2" />
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Used for every product without its own target. Recommended price = cost ÷ (1 − margin).') }}</p>
        </form>

        <x-stat :label="__('Products')" :value="$counts['all']" :href="route('products.costs', $keep)" />
        <x-stat :label="__('Priced below target')" :value="$counts['below_target']" :href="route('products.costs', ['show' => 'below_target'] + $keep)" :alert="$counts['below_target'] > 0" />
        <x-stat :label="__('Missing costs')" :value="$counts['incomplete']" :href="route('products.costs', ['show' => 'incomplete'] + $keep)" />
    </div>

    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <nav class="flex flex-wrap gap-2" aria-label="{{ __('Filter') }}">
            <x-pill :href="route('products.costs', $keep)" :active="$show === null" :count="$counts['all']">{{ __('All') }}</x-pill>
            <x-pill :href="route('products.costs', ['show' => 'below_target'] + $keep)" :active="$show === 'below_target'" :count="$counts['below_target']">{{ __('Priced below target') }}</x-pill>
            <x-pill :href="route('products.costs', ['show' => 'incomplete'] + $keep)" :active="$show === 'incomplete'" :count="$counts['incomplete']">{{ __('Missing costs') }}</x-pill>
        </nav>
        <form method="GET" class="flex gap-2 md:w-80">
            @if ($show)<input type="hidden" name="show" value="{{ $show }}">@endif
            <x-text-input name="search" type="search" :placeholder="__('Search by name or Saipos SKU…')" class="w-full" :value="$search" :aria-label="__('Search')" />
            <x-primary-button>{{ __('Filter') }}</x-primary-button>
        </form>
    </div>

    <x-table :headers="[
        __('Product'),
        ['label' => __('Cost'), 'align' => 'right'],
        ['label' => __('Price'), 'align' => 'right'],
        ['label' => __('Margin'), 'align' => 'right'],
        ['label' => __('Target'), 'align' => 'right'],
        ['label' => __('Recommended price'), 'align' => 'right'],
        ['label' => __('Details'), 'sr' => true],
    ]" groups>
        @forelse ($rows as $m)
            @php
                $partial = $m->missingCostItems !== [];
                $gap = $m->recommendedPrice !== null ? round($m->recommendedPrice - $m->price, 2) : null;
            @endphp
            <tbody x-data="{ open: false }" class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr class="align-top hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                    <td class="px-4 py-3 sm:px-6">
                        <a href="{{ route('products.edit', $m->productId) }}" class="font-medium text-gray-900 hover:text-indigo-700 dark:text-gray-100 dark:hover:text-indigo-300">{{ $m->name }}</a>
                        <span class="block font-mono text-xs text-gray-500 dark:text-gray-400">{{ $m->saiposSkuId }}</span>
                    </td>
                    <td data-label="{{ __('Cost') }}" class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                        @if ($m->error)
                            <x-badge color="red">{{ __('No recipe') }}</x-badge>
                        @else
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $money($m->cost) }}</span>@if ($partial)<span class="text-yellow-600 dark:text-yellow-400" title="{{ __('Some ingredients have no cost') }}">+</span>@endif
                        @endif
                    </td>
                    <td data-label="{{ __('Price') }}" class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $money($m->price) }}</td>
                    <td data-label="{{ __('Margin') }}" @class(['whitespace-nowrap px-4 py-3 text-right tabular-nums',
                        'text-red-600 dark:text-red-400' => $m->profit !== null && $m->profit < 0,
                        'text-yellow-700 dark:text-yellow-400' => $m->marginPercent !== null && $m->profit >= 0 && $m->marginPercent < $m->targetMarginPercent,
                        'text-gray-700 dark:text-gray-300' => $m->marginPercent === null || $m->marginPercent >= $m->targetMarginPercent])>
                        {{ $partial && $m->marginPercent !== null ? '≤ ' : '' }}{{ $percent($m->marginPercent) }}
                    </td>
                    <td data-label="{{ __('Target') }}" class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $percent($m->targetMarginPercent) }}</td>
                    <td data-label="{{ __('Recommended price') }}" class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                        <span class="font-semibold text-indigo-700 dark:text-indigo-300">{{ $money($m->recommendedPrice) }}@if ($partial && $m->recommendedPrice !== null)+@endif</span>
                        @if ($gap !== null && $gap > 0)
                            <span class="block text-xs text-yellow-700 dark:text-yellow-400">{{ __(':amount below', ['amount' => $money($gap)]) }}</span>
                        @endif
                    </td>
                    <td class="stack-actions px-4 py-3 text-right sm:px-6">
                        @unless ($m->error)
                            <button type="button" x-on:click="open = ! open" :aria-expanded="open.toString()"
                                    class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                <span x-text="open ? @js(__('Hide ingredients')) : @js(__('Ingredients'))">{{ __('Ingredients') }}</span>
                                <svg class="h-4 w-4 transition" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                            </button>
                        @endunless
                    </td>
                </tr>
                @unless ($m->error)
                    <tr x-show="open" x-cloak class="bg-gray-50/70 dark:bg-gray-800/30">
                        <td colspan="7" class="px-4 py-3 sm:px-6">
                            <ul class="grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($m->costBreakdown as $line)
                                    <li class="flex justify-between gap-3">
                                        <span class="text-gray-700 dark:text-gray-300">{{ \App\Support\Quantity::format($line['quantity'], $line['unit']) }} {{ $line['name'] }}</span>
                                        @if ($line['cost'] === null)
                                            <span class="whitespace-nowrap text-xs text-yellow-700 dark:text-yellow-400">{{ __('no cost') }}</span>
                                        @else
                                            <span class="whitespace-nowrap tabular-nums text-gray-900 dark:text-gray-100">{{ $money($line['cost']) }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endunless
            </tbody>
        @empty
            <tbody><x-empty-row colspan="7">{{ __('No products match.') }}</x-empty-row></tbody>
        @endforelse
    </x-table>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('Costs come from the cost per unit set in Inventory. A "+" means some ingredients have no cost yet, so the real cost (and recommended price) is higher.') }}
    </p>
</x-app-layout>
