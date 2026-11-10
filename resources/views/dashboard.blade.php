<x-app-layout>
    @php
        $money = fn ($v) => \App\Support\Money::format($v);
        $percent = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.').' %';
        $hour = now()->hour;
        $greeting = $hour < 12 ? __('Good morning') : ($hour < 18 ? __('Good afternoon') : __('Good evening'));
        $firstName = \Illuminate\Support\Str::of(Auth::user()->name)->trim()->before(' ');
        $monthPositive = $month['net'] >= 0;
    @endphp

    <x-slot name="title">{{ __('Dashboard') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="$greeting.', '.$firstName" :subtitle="ucfirst(now()->isoFormat('dddd, LL'))">
            <x-slot name="actions">
                <x-link-button :href="route('orders.create')" icon="plus">{{ __('New order') }}</x-link-button>
                <x-link-button variant="secondary" :href="route('manual-deductions.create')" icon="deduct">{{ __('Deduct stock') }}</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    {{-- Saipos sync status --}}
    <div @class([
        'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm',
        'border-gray-200/80 bg-white text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400' => $lastOrderAt,
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300' => ! $lastOrderAt,
    ])>
        <span class="relative mt-1.5 flex h-2 w-2 shrink-0">
            @if ($lastOrderAt)<span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-60"></span>@endif
            <span @class(['relative inline-flex h-2 w-2 rounded-full', 'bg-green-500' => $lastOrderAt, 'bg-amber-500' => ! $lastOrderAt])></span>
        </span>
        <p>
            @if ($lastOrderAt)
                {{ __('Last Saipos order received :time.', ['time' => \Illuminate\Support\Carbon::parse($lastOrderAt)->diffForHumans()]) }}
            @else
                {{ __('No Saipos orders received yet. Check that the poller is running and configured:') }}
                <code class="rounded bg-amber-100 px-1 dark:bg-amber-400/20">php artisan saipos:poll</code>
            @endif
        </p>
    </div>

    {{-- Money --}}
    <section aria-label="{{ __('Sales and results') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="__('Sales today')" :value="$money($today['revenue'])" icon="orders" :href="route('reports.pnl', ['period' => 'today'])"
                :hint="trans_choice('{0} no orders|{1} :count order|[2,*] :count orders', $today['orders_count'])" />
        <x-stat :label="__('Gross profit today')" :value="$money($today['gross_profit'])" icon="chart" :href="route('reports.pnl', ['period' => 'today'])"
                :hint="__('Margin :value', ['value' => $percent($today['gross_margin_percent'])])" />
        <x-stat :label="__('Sales this month')" :value="$money($month['revenue'])" icon="calculator" :href="route('reports.pnl', ['period' => 'month'])"
                :hint="trans_choice('{0} no orders|{1} :count order|[2,*] :count orders', $month['orders_count'])" />

        <a href="{{ route('reports.pnl', ['period' => 'month']) }}" @class([
            'group block min-w-0 rounded-xl border p-4 transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 sm:p-5',
            'border-green-200 bg-gradient-to-br from-green-50 to-white dark:border-green-500/30 dark:from-green-500/10 dark:to-gray-900' => $monthPositive,
            'border-red-200 bg-gradient-to-br from-red-50 to-white dark:border-red-500/30 dark:from-red-500/10 dark:to-gray-900' => ! $monthPositive,
        ])>
            <div class="flex items-start justify-between gap-3">
                <p @class(['eyebrow', '!text-green-800 dark:!text-green-300' => $monthPositive, '!text-red-800 dark:!text-red-300' => ! $monthPositive])>{{ __('Net result this month') }}</p>
                <x-badge :color="$monthPositive ? 'green' : 'red'" dot>{{ $monthPositive ? __('Profit') : __('Loss') }}</x-badge>
            </div>
            <p @class(['mt-1 truncate text-2xl font-semibold tabular-nums tracking-tight', 'text-green-800 dark:text-green-300' => $monthPositive, 'text-red-700 dark:text-red-300' => ! $monthPositive])>{{ $money($month['net']) }}</p>
            <p class="mt-1 truncate text-xs text-gray-600 dark:text-gray-400">{{ __('After losses :losses and expenses :expenses', ['losses' => $money($month['losses']), 'expenses' => $money($month['expenses'])]) }}</p>
        </a>
    </section>

    {{-- Operations --}}
    <section aria-label="{{ __('Operations') }}" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat :label="__('Orders deducted today')" :value="$processedToday" icon="check-circle" :href="route('orders.index', ['state' => 'processed'])" />
        <x-stat :label="__('Failed orders')" :value="$failedCount" icon="warning" :alert="$failedCount > 0" :href="route('orders.index', ['state' => 'failed'])" />
        <x-stat :label="__('Below minimum')" :value="$lowStockCount" icon="inventory" :alert="$lowStockCount > 0" :href="route('inventory.index', ['low' => 1])"
                :hint="__(':count items in stock', ['count' => $inventoryCount])" />
        <x-stat :label="__('Manual deductions today')" :value="$deductionsToday" icon="deduct" :href="route('manual-deductions.index')" />
    </section>

    @if ($unmappedProducts->isNotEmpty())
        <div class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300">
            <x-icon name="warning" class="mt-0.5 h-5 w-5 text-amber-500" />
            <div>
                <p class="font-medium">{{ trans_choice('{1} :count product not mapped to stock|[2,*] :count products not mapped to stock', $unmappedProducts->count()) }}</p>
                <p class="mt-1">
                    {{ __('Orders containing these will fail until you add a recipe or a standalone item:') }}
                    @foreach ($unmappedProducts as $product)
                        <a href="{{ route('products.edit', $product) }}" class="font-medium underline underline-offset-2">{{ $product->name }}</a>@if (! $loop->last), @endif
                    @endforeach
                </p>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2 3xl:grid-cols-3">
        <x-card :title="__('Low stock')" :description="__('Most urgent first')" :padding="false">
            <x-slot name="actions">
                <a href="{{ route('inventory.index', ['low' => 1]) }}" class="link text-sm">{{ __('View all') }}</a>
            </x-slot>
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($lowStockItems as $item)
                    @php($ratio = (float) $item->minimum_stock > 0 ? max(0, min(1, (float) $item->current_stock / (float) $item->minimum_stock)) : 0)
                    <li class="flex items-center justify-between gap-4 px-4 py-3 sm:px-5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item->name }}</p>
                            <div class="mt-1.5 flex items-center gap-2">
                                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" aria-hidden="true">
                                    <div class="h-full rounded-full bg-red-500" style="width: {{ round($ratio * 100) }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Minimum :amount', ['amount' => $item->formatQuantity($item->minimum_stock)]) }}</span>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="text-sm font-semibold tabular-nums text-red-600 dark:text-red-400">{{ $item->formatQuantity($item->current_stock) }}</span>
                            <x-link-button variant="secondary" class="h-8 px-2.5 text-xs" :href="route('inventory.receive', $item)" icon="receive">{{ __('Receive') }}</x-link-button>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-10 text-center">
                        <x-icon name="check-circle" class="mx-auto h-6 w-6 text-green-500" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Every item is above its minimum.') }}</p>
                    </li>
                @endforelse
            </ul>
        </x-card>

        <x-card :title="__('Failed orders')" :description="__('Stock was not deducted')" :padding="false">
            <x-slot name="actions">
                <a href="{{ route('orders.index', ['state' => 'failed']) }}" class="link text-sm">{{ __('View all') }}</a>
            </x-slot>
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($failedOrders as $order)
                    <li class="flex items-start justify-between gap-4 px-4 py-3 sm:px-5">
                        <div class="min-w-0">
                            <a href="{{ route('orders.show', $order) }}" class="font-mono text-sm font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400">{{ $order->saipos_order_id }}</a>
                            <p class="mt-0.5 break-words text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($order->last_error, 110) }}</p>
                        </div>
                        <form method="POST" action="{{ route('orders.retry', $order) }}" class="shrink-0">
                            @csrf
                            <button class="btn btn-secondary h-8 px-2.5 text-xs"><x-icon name="retry" class="h-3.5 w-3.5" />{{ __('Retry') }}</button>
                        </form>
                    </li>
                @empty
                    <li class="px-6 py-10 text-center">
                        <x-icon name="check-circle" class="mx-auto h-6 w-6 text-green-500" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No failed orders.') }}</p>
                    </li>
                @endforelse
            </ul>
        </x-card>

        <x-card :title="__('Recent manual deductions')" :padding="false" class="lg:col-span-2 3xl:col-span-1">
            <x-slot name="actions">
                <a href="{{ route('manual-deductions.index') }}" class="link text-sm">{{ __('View all') }}</a>
            </x-slot>
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($recentDeductions as $deduction)
                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 text-sm sm:px-5">
                        <span class="flex min-w-0 items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400"><x-icon name="deduct" class="h-4 w-4" /></span>
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-gray-900 dark:text-gray-100">{{ $deduction->targetName() }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __($deduction->reason) }} · {{ $deduction->user?->name ?? __('Command line') }}, {{ $deduction->created_at->diffForHumans() }}</span>
                            </span>
                        </span>
                        <span class="font-medium tabular-nums text-gray-900 dark:text-gray-100">−{{ $deduction->formattedQuantity() }}</span>
                    </li>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No manual deductions yet.') }}</li>
                @endforelse
            </ul>
        </x-card>
    </div>
</x-app-layout>
