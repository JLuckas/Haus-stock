<x-app-layout>
    <x-slot name="title">{{ __('Products') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Products')" :subtitle="__('Items sold in Saipos and what each one takes from stock.')">
            <x-slot name="actions">
                <x-link-button :href="route('products.create')">{{ __('Add product') }}</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-product-tabs />

    <form method="GET" class="flex flex-col gap-3 md:flex-row md:items-center">
        <div class="md:flex-1">
            <x-input-label for="search" :value="__('Search')" class="sr-only" />
            <x-text-input id="search" name="search" type="search" :placeholder="__('Search by name or Saipos SKU…')" class="w-full" :value="$filters['search'] ?? ''" />
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="unmapped" value="1" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-950 dark:checked:bg-indigo-500 dark:focus:ring-offset-gray-900" @checked($filters['unmapped'] ?? false)>
            {{ __('Not mapped to stock only') }}
        </label>
        <div class="flex gap-2">
            <x-primary-button class="flex-1 md:flex-none">{{ __('Filter') }}</x-primary-button>
            @if (array_filter($filters))
                <x-link-button variant="secondary" :href="route('products.index')" class="flex-1 md:flex-none">{{ __('Clear') }}</x-link-button>
            @endif
        </div>
    </form>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="table-stack min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                <thead class="bg-gray-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800/40 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3 sm:px-6">{{ __('Product') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Saipos SKU') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('Price') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Takes from stock') }}</th>
                        <th scope="col" class="px-4 py-3 sm:px-6"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($products as $product)
                        @php($mode = $product->stockMode())
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/40 align-top">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100 sm:px-6">{{ $product->name }}</td>
                            <td data-label="{{ __('Saipos SKU') }}" class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $product->saipos_sku_id }}</td>
                            <td data-label="{{ __('Price') }}" class="px-4 py-3 text-right tabular-nums whitespace-nowrap">{{ \App\Support\Money::format($product->price) }}</td>
                            <td data-label="{{ __('Takes from stock') }}" class="stack-block px-4 py-3 min-w-[16rem]">
                                <div>
                                    @if ($mode === \App\Enums\ProductStockMode::Standalone)
                                        <x-badge color="blue">{{ __('Standalone') }}</x-badge>
                                        <span class="ml-1 text-gray-700 dark:text-gray-300">1 un {{ $product->standaloneItem?->name }}</span>
                                    @elseif ($mode === \App\Enums\ProductStockMode::Recipe)
                                        <x-badge :color="$product->components->isNotEmpty() ? 'yellow' : 'indigo'">{{ $product->components->isNotEmpty() ? __('Combo') : __('Recipe') }}</x-badge>
                                        <span class="ml-1 text-gray-700 dark:text-gray-300">
                                            {{ $product->components->toBase()->map(fn ($c) => \App\Support\Quantity::format($c->pivot->quantity).' × '.$c->name)
                                                ->merge($product->ingredients->map(fn ($i) => $i->formatQuantity($i->pivot->quantity_needed).' '.$i->name))
                                                ->join(' · ') }}
                                        </span>
                                    @else
                                        <x-badge color="red">{{ __('Not mapped') }}</x-badge>
                                        <span class="ml-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Orders with this product will fail.') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="stack-actions px-4 py-3 sm:px-6">
                                <div class="flex justify-end gap-4 whitespace-nowrap text-sm font-medium">
                                    @if ($mode)
                                        <a href="{{ route('manual-deductions.create', ['product' => $product->id]) }}" class="py-1 text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Deduct') }}</a>
                                    @endif
                                    <a href="{{ route('products.edit', $product) }}" class="py-1 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">{{ __('Edit') }}</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">{{ __('No products match these filters.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{ $products->links() }}
</x-app-layout>
