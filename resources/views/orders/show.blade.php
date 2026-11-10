<x-app-layout>
    @php($state = $order->stockState())

    <x-slot name="title">{{ __('Order :order', ['order' => $order->saipos_order_id]) }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Order :order', ['order' => $order->saipos_order_id])">
            <x-slot name="actions">
                @if ($order->canRetry())
                    <form method="POST" action="{{ route('orders.retry', $order) }}">
                        @csrf
                        <x-primary-button>{{ __('Retry stock deduction') }}</x-primary-button>
                    </form>
                @endif
                <x-link-button variant="secondary" :href="route('orders.index')">{{ __('Back to orders') }}</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @if ($order->last_error && ! $order->is_stock_processed)
        <div class="rounded-lg bg-red-50 dark:bg-red-500/10 p-4 text-sm text-red-800 dark:text-red-300 ring-1 ring-red-200 dark:ring-red-500/30" role="alert">
            <p class="font-medium">{{ __('Stock was not deducted') }}</p>
            <p class="mt-1 break-words">{{ $order->last_error }}</p>
            <p class="mt-2 text-red-700 dark:text-red-400">{{ __('Fix the cause, for example register the missing product below, then click "Retry stock deduction".') }}</p>
        </div>
    @endif

    <x-card>
        <dl class="grid grid-cols-2 gap-4 text-sm lg:grid-cols-4">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Stock') }}</dt>
                <dd class="mt-1"><x-badge :color="$state->color()">{{ $state->label() }}</x-badge></dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Saipos status') }}</dt>
                <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $order->status ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Received') }}</dt>
                <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $order->created_at->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Stock deducted') }}</dt>
                <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $order->stock_processed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Origin') }}</dt>
                <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">
                    {{ $order->source->label() }}@if ($order->isManual() && $order->creator) · {{ $order->creator->name }}@endif
                </dd>
            </div>
            @if ($order->notes)
                <div class="col-span-2 lg:col-span-3">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Notes') }}</dt>
                    <dd class="mt-1 whitespace-pre-line break-words text-gray-900 dark:text-gray-100">{{ $order->notes }}</dd>
                </div>
            @endif
        </dl>
    </x-card>

    <x-card :title="__('Items')" :padding="false">
        <div class="overflow-x-auto">
            <table class="table-stack min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                <thead class="bg-gray-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800/40 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3 sm:px-6">{{ __('Item') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Saipos SKU') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('Qty') }}</th>
                        <th scope="col" class="px-4 py-3 sm:px-6">{{ __('Local product') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($items as $line)
                        @php($product = $line->skuId ? $products->get($line->skuId) : null)
                        <tr class="align-top">
                            <td @class(['px-4 py-3 sm:px-6 text-gray-900 dark:text-gray-100', 'lg:pl-10 text-gray-600 dark:text-gray-400' => $line->isChoice])>
                                @if ($line->isChoice)<span class="text-gray-400 dark:text-gray-500">+ </span>@endif{{ $line->name ?: '—' }}
                                @if ($line->isChoice)<x-badge class="ml-1">{{ __('Add-on') }}</x-badge>@endif
                            </td>
                            <td data-label="{{ __('Saipos SKU') }}" class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $line->skuId ?? '—' }}</td>
                            <td data-label="{{ __('Qty') }}" class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Quantity::format($line->quantity) }}</td>
                            <td data-label="{{ __('Local product') }}" class="px-4 py-3 sm:px-6">
                                <div>
                                    @if ($product)
                                        <a href="{{ route('products.edit', $product) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ $product->name }}</a>
                                    @elseif ($line->isChoice)
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Not tracked (ignored)') }}</span>
                                    @else
                                        <x-badge color="red">{{ __('Not registered') }}</x-badge>
                                        @if ($line->skuId)
                                            <a href="{{ route('products.create', ['sku' => $line->skuId, 'name' => $line->name]) }}" class="ml-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Register product') }}</a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('The stored payload has no items.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <details class="surface">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-800 dark:text-gray-200 sm:px-6">{{ __('Raw Saipos payload') }}</summary>
        <pre class="overflow-x-auto border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/60 p-4 text-xs text-gray-700 dark:text-gray-300 sm:px-6">{{ json_encode($order->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </details>
</x-app-layout>
