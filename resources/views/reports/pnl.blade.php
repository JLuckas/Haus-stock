<x-app-layout>
    @php
        $money = fn ($v) => \App\Support\Money::format($v);
        $percent = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.').' %';
        $r = $report;
        $buckets = $r['chart']['buckets'];

        // Diverging columns: gross profit up from zero; negative gross profit, losses and expenses stacked down.
        $up = array_map(fn ($b) => max($b['gross_profit'], 0), $buckets);
        $down = array_map(fn ($b) => max(-$b['gross_profit'], 0) + $b['losses'] + $b['expenses'], $buckets);
        $maxUp = $up ? max($up) : 0;
        $maxDown = $down ? max($down) : 0;
        $range = $maxUp + $maxDown;
        $zero = $range > 0 ? $maxUp / $range * 100 : 50;
        $pct = fn (float $v) => $range > 0 ? $v / $range * 100 : 0;
        $labelEvery = max(1, (int) ceil(count($buckets) / 10));

        $tooltip = array_map(fn ($b) => [
            'title' => $b['title'],
            'gross' => $money($b['gross_profit']),
            'losses' => $money(-$b['losses']),
            'expenses' => $money(-$b['expenses']),
            'net' => $money($b['net']),
            'negative' => $b['net'] < 0,
        ], $buckets);

        $incomplete = $r['incomplete'];
        $periodLabel = $period->label();
        $byKind = $r['expenses_by_kind'];
        $expenseMax = collect($byKind['by_label'])->max('total') ?: 0;
        $expensesLink = route('expenses.index', ['kind' => 'personnel'] + $period->query());
    @endphp

    <x-slot name="title">{{ __('Results') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Finance')" :subtitle="__('Profit from sales, minus losses from stock that left without a sale and the store expenses.')" />
    </x-slot>

    <x-finance-tabs />

    <x-period-filter :period="$period" route="reports.pnl" />

    {{-- Headline numbers --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 2xl:grid-cols-6">
        <div class="surface p-4 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Revenue') }}</p>
            <p class="mt-1 truncate text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $money($r['revenue']) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ trans_choice('{0} no orders|{1} :count order|[2,*] :count orders', $r['orders_count']) }}@if ($r['discounts'] > 0) · {{ __(':amount in discounts', ['amount' => $money($r['discounts'])]) }}@endif
            </p>
        </div>
        <div class="surface p-4 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Food cost (CMV)') }}</p>
            <p class="mt-1 truncate text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $money($r['cogs']) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Cost of what was sold') }}</p>
        </div>
        <div class="surface p-4 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Gross profit') }}</p>
            <p class="mt-1 truncate text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $money($r['gross_profit']) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Margin :value', ['value' => $percent($r['gross_margin_percent'])]) }}</p>
        </div>
        <div class="surface p-4 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Losses') }}</p>
            <p class="mt-1 truncate text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $money(-$r['losses']) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ trans_choice('{0} no manual deductions|{1} :count manual deduction|[2,*] :count manual deductions', $r['deductions_count']) }}</p>
        </div>
        <a href="{{ $expensesLink }}" class="surface p-4 sm:p-5 transition hover:border-gray-300 hover:shadow-md dark:hover:border-gray-700">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Expenses') }}</p>
            <p class="mt-1 truncate text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $money(-$r['expenses']) }}</p>
            <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ __('Personnel') }} {{ $money($byKind['personnel']) }} · {{ __('General') }} {{ $money($byKind['general']) }}</p>
        </a>
        @php($positive = $r['net'] >= 0)
        <div @class(['rounded-lg p-4 ring-1',
            'bg-green-50 ring-green-200 dark:bg-green-500/10 dark:ring-green-500/30' => $positive,
            'bg-red-50 ring-red-200 dark:bg-red-500/10 dark:ring-red-500/30' => ! $positive])>
            <p @class(['text-xs font-medium uppercase tracking-wide', 'text-green-800 dark:text-green-300' => $positive, 'text-red-800 dark:text-red-300' => ! $positive])>
                {{ __('Net result') }} · {{ $positive ? __('Profit') : __('Loss') }}
            </p>
            <p @class(['mt-1 truncate text-3xl font-bold', 'text-green-800 dark:text-green-300' => $positive, 'text-red-700 dark:text-red-300' => ! $positive])>{{ $money($r['net']) }}</p>
            <p @class(['mt-1 text-xs', 'text-green-700 dark:text-green-400' => $positive, 'text-red-700 dark:text-red-400' => ! $positive])>{{ __('Gross profit − losses − expenses') }} · {{ $periodLabel }}</p>
        </div>
    </div>

    @if ($incomplete['orders'] + $incomplete['deductions'] + $incomplete['unvalued'] > 0)
        <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30">
            @if ($incomplete['orders'] + $incomplete['deductions'] > 0)
                <p>{{ __(':orders orders and :deductions deductions used ingredients without a cost, so costs are understated and the result is better than reality.', ['orders' => $incomplete['orders'], 'deductions' => $incomplete['deductions']]) }}
                    <a href="{{ route('inventory.index') }}" class="font-medium underline">{{ __('Set costs in Inventory') }}</a>.</p>
            @endif
            @if ($incomplete['unvalued'] > 0)
                <p class="mt-1">{{ __(':count records from before this report have no saved values yet. Run :command to value them at today\'s costs.', ['count' => $incomplete['unvalued'], 'command' => 'php artisan finance:backfill']) }}</p>
            @endif
        </div>
    @endif

    {{-- Daily (or weekly) result: profit up, losses down. --}}
    <x-card :title="$r['chart']['granularity'] === 'week' ? __('Result per week') : __('Result per day')">
        <x-slot name="actions">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
                <span class="flex items-center gap-1.5"><span class="chart-pos inline-block h-2.5 w-2.5 rounded-sm"></span>{{ __('Gross profit') }}</span>
                <span class="flex items-center gap-1.5"><span class="chart-neg inline-block h-2.5 w-2.5 rounded-sm"></span>{{ __('Losses') }}</span>
                <span class="flex items-center gap-1.5"><span class="chart-exp inline-block h-2.5 w-2.5 rounded-sm"></span>{{ __('Expenses') }}</span>
            </div>
        </x-slot>

        @if ($range <= 0)
            <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No sales, losses or expenses in this period.') }}</p>
        @else
            <div class="relative" x-data="{ hover: null, data: @js($tooltip), count: {{ count($buckets) }} }">
                {{-- Tooltip: values lead, labels follow. --}}
                <div x-show="hover !== null" x-cloak
                     class="pointer-events-none absolute top-0 z-10 w-48 -translate-x-1/2 rounded-md bg-white p-3 text-xs shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                     :style="hover !== null && `left: clamp(6rem, ${(hover + 0.5) / count * 100}%, calc(100% - 6rem))`">
                    <p class="mb-1.5 font-medium text-gray-500 dark:text-gray-400" x-text="hover !== null ? data[hover].title : ''"></p>
                    <p class="flex items-center justify-between gap-2"><span class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400"><span class="chart-pos inline-block h-0.5 w-3"></span>{{ __('Gross profit') }}</span><span class="font-semibold text-gray-900 dark:text-gray-100" x-text="hover !== null ? data[hover].gross : ''"></span></p>
                    <p class="flex items-center justify-between gap-2"><span class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400"><span class="chart-neg inline-block h-0.5 w-3"></span>{{ __('Losses') }}</span><span class="font-semibold text-gray-900 dark:text-gray-100" x-text="hover !== null ? data[hover].losses : ''"></span></p>
                    <p class="flex items-center justify-between gap-2"><span class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400"><span class="chart-exp inline-block h-0.5 w-3"></span>{{ __('Expenses') }}</span><span class="font-semibold text-gray-900 dark:text-gray-100" x-text="hover !== null ? data[hover].expenses : ''"></span></p>
                    <p class="mt-1.5 flex justify-between gap-2 border-t border-gray-100 pt-1.5 dark:border-gray-700"><span class="text-gray-600 dark:text-gray-400">{{ __('Net result') }}</span><span class="font-bold" :class="hover !== null && data[hover].negative ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'" x-text="hover !== null ? data[hover].net : ''"></span></p>
                </div>

                <div class="flex gap-2">
                    {{-- Y axis: top of the profit scale, zero, bottom of the loss scale --}}
                    <div class="relative h-56 w-16 shrink-0 text-right text-[11px] tabular-nums text-gray-500 dark:text-gray-400 sm:h-64">
                        @if ($maxUp > 0)<span class="absolute right-0 top-0 -translate-y-1/2">{{ $money($maxUp) }}</span>@endif
                        <span class="absolute right-0 -translate-y-1/2" style="top: {{ $zero }}%">R$ 0</span>
                        @if ($maxDown > 0)<span class="absolute bottom-0 right-0 translate-y-1/2">{{ $money(-$maxDown) }}</span>@endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="relative h-56 sm:h-64" role="img" aria-label="{{ __('Result per day') }}: {{ __('see the data table below') }}">
                            {{-- recessive hairline grid: top, zero, bottom --}}
                            <div class="absolute inset-x-0 top-0 border-t border-gray-100 dark:border-gray-800"></div>
                            <div class="absolute inset-x-0 border-t border-gray-300 dark:border-gray-600" style="top: {{ $zero }}%"></div>
                            <div class="absolute inset-x-0 bottom-0 border-t border-gray-100 dark:border-gray-800"></div>

                            <div class="absolute inset-0 flex">
                                @foreach ($buckets as $i => $b)
                                    @php($negGross = max(-$b['gross_profit'], 0))
                                    <div class="relative flex-1 outline-none" tabindex="0"
                                         x-on:mouseenter="hover = {{ $i }}" x-on:mouseleave="hover = null" x-on:focus="hover = {{ $i }}" x-on:blur="hover = null"
                                         :class="hover === {{ $i }} && 'bg-gray-100/70 dark:bg-gray-800/60'"
                                         aria-label="{{ $b['title'] }}: {{ __('Gross profit') }} {{ $money($b['gross_profit']) }}, {{ __('Losses') }} {{ $money(-$b['losses']) }}, {{ __('Expenses') }} {{ $money(-$b['expenses']) }}, {{ __('Net result') }} {{ $money($b['net']) }}">
                                        @if ($up[$i] > 0)
                                            <div class="chart-pos absolute left-1/2 w-3/5 max-w-[24px] -translate-x-1/2 rounded-t" style="bottom: {{ 100 - $zero }}%; height: {{ $pct($up[$i]) }}%"></div>
                                        @endif
                                        @if ($negGross > 0)
                                            <div @class(['chart-pos absolute left-1/2 w-3/5 max-w-[24px] -translate-x-1/2', 'rounded-b' => $b['losses'] + $b['expenses'] <= 0]) style="top: {{ $zero }}%; height: {{ $pct($negGross) }}%"></div>
                                        @endif
                                        @if ($b['losses'] > 0)
                                            {{-- 2px surface gap between stacked segments --}}
                                            <div @class(['chart-neg absolute left-1/2 w-3/5 max-w-[24px] -translate-x-1/2', 'rounded-b' => $b['expenses'] <= 0]) style="top: calc({{ $zero + $pct($negGross) }}% + {{ $negGross > 0 ? 2 : 0 }}px); height: {{ $pct($b['losses']) }}%"></div>
                                        @endif
                                        @if ($b['expenses'] > 0)
                                            @php($gaps = ($negGross > 0 ? 2 : 0) + ($b['losses'] > 0 ? 2 : 0))
                                            <div class="chart-exp absolute left-1/2 w-3/5 max-w-[24px] -translate-x-1/2 rounded-b" style="top: calc({{ $zero + $pct($negGross + $b['losses']) }}% + {{ $gaps }}px); height: {{ $pct($b['expenses']) }}%"></div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-2 flex text-[11px] text-gray-500 dark:text-gray-400">
                            @foreach ($buckets as $i => $b)
                                <span class="flex-1 text-center">{{ $i % $labelEvery === 0 ? $b['label'] : '' }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <details class="mt-4 text-sm">
                <summary class="cursor-pointer font-medium text-indigo-600 dark:text-indigo-400">{{ __('See the data as a table') }}</summary>
                <div class="mt-3 max-h-80 overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="py-1 pr-4">{{ $r['chart']['granularity'] === 'week' ? __('Week') : __('Day') }}</th>
                                <th class="py-1 pr-4 text-right">{{ __('Gross profit') }}</th>
                                <th class="py-1 pr-4 text-right">{{ __('Losses') }}</th>
                                <th class="py-1 pr-4 text-right">{{ __('Expenses') }}</th>
                                <th class="py-1 text-right">{{ __('Net result') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 tabular-nums text-gray-700 dark:divide-gray-800 dark:text-gray-300">
                            @foreach ($buckets as $b)
                                <tr>
                                    <td class="py-1 pr-4">{{ $b['title'] }}</td>
                                    <td class="py-1 pr-4 text-right">{{ $money($b['gross_profit']) }}</td>
                                    <td class="py-1 pr-4 text-right">{{ $money(-$b['losses']) }}</td>
                                    <td class="py-1 pr-4 text-right">{{ $money(-$b['expenses']) }}</td>
                                    <td @class(['py-1 text-right font-medium', 'text-red-600 dark:text-red-400' => $b['net'] < 0])>{{ $money($b['net']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </x-card>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Losses by reason --}}
        <x-card :title="__('Losses by reason')" :padding="false">
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($r['losses_by_reason'] as $row)
                    <li class="px-4 py-3 sm:px-6">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="min-w-0 font-medium text-gray-900 dark:text-gray-100">{{ __($row['reason']) }}
                                <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $row['count'] }}×</span></span>
                            <span class="whitespace-nowrap tabular-nums text-gray-900 dark:text-gray-100">{{ $money(-$row['cost']) }}</span>
                        </div>
                        <div class="mt-1.5 flex items-center gap-2">
                            <div class="h-1.5 flex-1 rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="chart-neg h-1.5 rounded-full" style="width: {{ $row['share_percent'] }}%"></div>
                            </div>
                            <span class="w-12 text-right text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $percent($row['share_percent']) }}</span>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No losses in this period.') }}</li>
                @endforelse
            </ul>
        </x-card>

        {{-- Expenses by role / category --}}
        <x-card :title="__('Expenses')" :padding="false">
            <x-slot name="actions">
                <a href="{{ $expensesLink }}" class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ __('Open expenses') }}</a>
            </x-slot>
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($byKind['by_label'] as $row)
                    <li class="px-4 py-3 sm:px-6">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="min-w-0 font-medium text-gray-900 dark:text-gray-100">{{ $row['label'] }}</span>
                            <span class="whitespace-nowrap tabular-nums text-gray-900 dark:text-gray-100">{{ $money(-$row['total']) }}</span>
                        </div>
                        <div class="mt-1.5 flex items-center gap-2">
                            <div class="h-1.5 flex-1 rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="chart-exp h-1.5 rounded-full" style="width: {{ $expenseMax > 0 ? $row['total'] / $expenseMax * 100 : 0 }}%"></div>
                            </div>
                            <span class="w-12 text-right text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $percent($r['expenses'] > 0 ? $row['total'] / $r['expenses'] * 100 : null) }}</span>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No expenses in this period.') }}</li>
                @endforelse
            </ul>
        </x-card>
    </div>

    {{-- Profit per product --}}
    <div>
        <x-card :title="__('Profit per product')" :padding="false">
            <div class="overflow-x-auto">
                <table class="table-stack min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800/40 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 sm:px-6">{{ __('Product') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('Qty') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('Revenue') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('Cost') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('Profit') }}</th>
                            <th scope="col" class="px-4 py-3 text-right sm:px-6">{{ __('Margin') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($r['products'] as $p)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-3 sm:px-6">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $p['name'] }}</span>
                                    @if ($p['is_add_on'])<x-badge class="ml-1">{{ __('Add-on') }}</x-badge>@endif
                                    @unless ($p['cost_is_complete'])<span class="ml-1 text-xs text-yellow-700 dark:text-yellow-400" title="{{ __('Some ingredients have no cost') }}">{{ __('cost incomplete') }}</span>@endunless
                                </td>
                                <td data-label="{{ __('Qty') }}" class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ \App\Support\Quantity::format($p['quantity']) }}</td>
                                <td data-label="{{ __('Revenue') }}" class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $money($p['revenue']) }}</td>
                                <td data-label="{{ __('Cost') }}" class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $money($p['cost']) }}</td>
                                <td data-label="{{ __('Profit') }}" @class(['px-4 py-3 text-right tabular-nums whitespace-nowrap font-medium', 'text-red-600 dark:text-red-400' => $p['profit'] < 0, 'text-gray-900 dark:text-gray-100' => $p['profit'] >= 0])>{{ $money($p['profit']) }}</td>
                                <td data-label="{{ __('Margin') }}" class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-400 sm:px-6">{{ $percent($p['margin_percent']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('No sales in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('Orders count on the day their stock was deducted (failed orders are not included); manual deductions on the day they were registered. Values use the ingredient costs saved at that moment.') }}
        {{ __('Expenses count on the date they were paid.') }}
    </p>
</x-app-layout>
