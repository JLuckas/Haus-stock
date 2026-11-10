<x-app-layout>
    <x-slot name="title">{{ __('Orders') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Orders')" :subtitle="__('Orders imported from Saipos or registered by hand, and whether their stock was deducted.')">
            <x-slot name="actions">
                <x-link-button :href="route('orders.create')">{{ __('New order') }}</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <nav class="flex flex-wrap gap-2" aria-label="{{ __('Filter by stock state') }}">
            @php($current = $filters['state'] ?? null)
            <x-pill :href="route('orders.index', array_filter(['search' => $filters['search'] ?? null]))" :active="! $current">{{ __('All') }}</x-pill>
            @foreach (\App\Enums\OrderStockState::cases() as $state)
                <x-pill :href="route('orders.index', array_filter(['state' => $state->value, 'search' => $filters['search'] ?? null]))" :active="$current === $state->value">{{ $state->label() }}</x-pill>
            @endforeach
        </nav>

        <form method="GET" class="flex gap-2">
            @if ($current)
                <input type="hidden" name="state" value="{{ $current }}">
            @endif
            <x-text-input name="search" type="search" :placeholder="__('Saipos order ID…')" :value="$filters['search'] ?? ''" class="w-full min-w-0 lg:w-64" :aria-label="__('Search by Saipos order ID')" />
            <x-primary-button class="shrink-0">{{ __('Search') }}</x-primary-button>
        </form>
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="table-stack min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                <thead class="bg-gray-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800/40 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3 sm:px-6">{{ __('Saipos order') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Stock') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Saipos status') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Received') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Problem') }}</th>
                        <th scope="col" class="px-4 py-3 sm:px-6"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($orders as $order)
                        @php($state = $order->stockState())
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/40 align-top">
                            <td class="px-4 py-3 sm:px-6">
                                <a href="{{ route('orders.show', $order) }}" class="font-mono font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ $order->saipos_order_id }}</a>
                                @if ($order->isManual())
                                    <x-badge color="indigo" class="ml-1" :title="$order->creator ? __('Registered by :name', ['name' => $order->creator->name]) : null">{{ __('Manual') }}</x-badge>
                                @endif
                            </td>
                            <td data-label="{{ __('Stock') }}" class="px-4 py-3"><x-badge :color="$state->color()">{{ $state->label() }}</x-badge></td>
                            <td data-label="{{ __('Saipos status') }}" class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $order->status ?? '—' }}</td>
                            <td data-label="{{ __('Received') }}" class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400" title="{{ $order->created_at->format('d/m/Y H:i') }}">{{ $order->created_at->diffForHumans() }}</td>
                            <td @if ($order->last_error) data-label="{{ __('Problem') }}" @endif @class(['stack-block px-4 py-3 text-gray-600 dark:text-gray-400 lg:max-w-md 3xl:max-w-2xl', 'hidden lg:table-cell' => ! $order->last_error])>
                                {{ \Illuminate\Support\Str::limit($order->last_error, 120) ?: '—' }}
                            </td>
                            <td class="stack-actions px-4 py-3 sm:px-6">
                                <div class="flex justify-end gap-4 whitespace-nowrap">
                                    @if ($order->canRetry())
                                        <form method="POST" action="{{ route('orders.retry', $order) }}">
                                            @csrf
                                            <button class="py-1 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Retry') }}</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('orders.show', $order) }}" class="py-1 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">{{ __('View') }}</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                {{ __('No orders yet. Orders appear here once the Saipos poller is running:') }}
                                <code class="rounded bg-gray-100 dark:bg-gray-800 px-1">php artisan saipos:poll</code>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{ $orders->links() }}
</x-app-layout>
