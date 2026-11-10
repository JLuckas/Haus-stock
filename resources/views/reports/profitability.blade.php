<x-app-layout>
    @php
        $percent = fn (?float $value): string => $value === null ? '—' : number_format($value, 1, ',', '.').' %';
        $statusCounts = $summary['by_status'];
    @endphp

    <x-slot name="title">{{ __('Profitability') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Profitability')"
                       :subtitle="__('Production cost from each recipe vs. selling price. A healthy margin is :value or more.', ['value' => $percent($threshold)])" />
    </x-slot>

    <x-finance-tabs />

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5">
        <x-stat :label="__('Average margin')" :value="$percent($summary['average_margin_percent'])" :href="route('reports.profitability')" />
        <x-stat :label="__('Selling at a loss')" :value="$statusCounts['loss']" :alert="$statusCounts['loss'] > 0" :href="route('reports.profitability', ['status' => 'loss'])" />
        <x-stat :label="__('Low margin')" :value="$statusCounts['below_threshold']" :alert="$statusCounts['below_threshold'] > 0" :href="route('reports.profitability', ['status' => 'below_threshold'])" />
        <x-stat :label="__('Missing costs')" :value="$statusCounts['incomplete_cost'] + $statusCounts['unmapped']" :alert="$statusCounts['incomplete_cost'] + $statusCounts['unmapped'] > 0" :href="route('reports.profitability', ['status' => 'incomplete_cost'])" />
        <x-stat :label="__('Healthy')" :value="$statusCounts['healthy'].' / '.$summary['products']" :href="route('reports.profitability', ['status' => 'healthy'])" class="col-span-2 sm:col-span-1" />
    </div>

    @if ($statusCounts['incomplete_cost'] > 0)
        <div class="rounded-lg bg-amber-50 dark:bg-amber-400/10 p-4 text-sm text-amber-800 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-amber-400/30">
            {{ __('Some ingredients have no cost yet, so those products look cheaper (and more profitable) than they are.') }}
            <a href="{{ route('inventory.index') }}" class="font-medium underline">{{ __('Set costs in Inventory') }}</a>.
        </div>
    @endif

    <nav class="flex flex-wrap gap-2" aria-label="{{ __('Filter by status') }}">
        <x-pill :href="route('reports.profitability')" :active="! $currentStatus">{{ __('All') }}</x-pill>
        @foreach (\App\Enums\ProfitabilityStatus::cases() as $status)
            <x-pill :href="route('reports.profitability', ['status' => $status->value])" :active="$currentStatus === $status" :count="$statusCounts[$status->value]">{{ $status->label() }}</x-pill>
        @endforeach
    </nav>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="table-stack min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                <thead class="bg-gray-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800/40 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3 sm:px-6">{{ __('Product') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('Price') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('Cost') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('Profit') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('Margin') }}</th>
                        <th scope="col" class="px-4 py-3 text-right" title="{{ __('Cost ÷ price (CMV)') }}">{{ __('Food cost') }}</th>
                        <th scope="col" class="px-4 py-3 sm:px-6">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($metrics as $m)
                        {{-- With missing costs, profit and margin are upper bounds ("at most"), so they're shown muted. --}}
                        @php($upperBound = $m->missingCostItems !== [] && ($m->profit ?? 0) >= 0)
                        <tr class="align-top hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 sm:px-6">
                                <a href="{{ route('products.edit', $m->productId) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:text-indigo-700 dark:hover:text-indigo-300">{{ $m->name }}</a>
                                @if ($m->error)
                                    <p class="mt-0.5 max-w-md text-xs text-red-700 dark:text-red-400">{{ $m->error }}</p>
                                @elseif ($m->missingCostItems)
                                    <p class="mt-0.5 max-w-md text-xs text-yellow-700 dark:text-yellow-400">{{ __('No cost for: :items', ['items' => implode(', ', $m->missingCostItems)]) }}</p>
                                @endif
                            </td>
                            <td data-label="{{ __('Price') }}" class="px-4 py-3 text-right tabular-nums whitespace-nowrap">{{ \App\Support\Money::format($m->price) }}</td>
                            <td data-label="{{ __('Cost') }}" class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-400">
                                <span>{{ $m->cost === null ? '—' : \App\Support\Money::format($m->cost) }}@if ($m->missingCostItems)<span class="text-yellow-700 dark:text-yellow-400" title="{{ __('Some ingredients have no cost') }}">+</span>@endif</span>
                            </td>
                            <td data-label="{{ __('Profit') }}" @class(['px-4 py-3 text-right tabular-nums whitespace-nowrap font-medium', 'text-red-600 dark:text-red-400' => ($m->profit ?? 0) < 0, 'text-gray-400 dark:text-gray-500' => $upperBound, 'text-gray-900 dark:text-gray-100' => ($m->profit ?? 0) >= 0 && ! $upperBound])>
                                {{ $m->profit === null ? '—' : ($upperBound ? '≤ ' : '').\App\Support\Money::format($m->profit) }}
                            </td>
                            <td data-label="{{ __('Margin') }}" @class(['px-4 py-3 text-right tabular-nums whitespace-nowrap font-medium',
                                'text-red-600 dark:text-red-400' => ($m->marginPercent ?? 0) < 0,
                                'text-gray-400 dark:text-gray-500' => $upperBound,
                                'text-yellow-700 dark:text-yellow-400' => ! $upperBound && $m->marginPercent !== null && $m->marginPercent >= 0 && $m->marginPercent < $threshold,
                                'text-green-700 dark:text-green-400' => ! $upperBound && ($m->marginPercent ?? -1) >= $threshold])>
                                {{ $m->marginPercent === null ? '—' : ($upperBound ? '≤ ' : '').$percent($m->marginPercent) }}
                            </td>
                            <td data-label="{{ __('Food cost') }}" class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $percent($m->foodCostPercent) }}</td>
                            <td data-label="{{ __('Status') }}" class="px-4 py-3 sm:px-6"><x-badge :color="$m->status->color()">{{ $m->status->label() }}</x-badge></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">{{ __('No products with this status.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('Margin = profit ÷ price. Food cost (CMV) = cost ÷ price. The same data is available as JSON with an API token:') }}
        <code class="rounded bg-gray-100 dark:bg-gray-800 px-1">GET /api/reports/profitability</code> (<code class="rounded bg-gray-100 dark:bg-gray-800 px-1">php artisan api:token</code>).
    </p>
</x-app-layout>
