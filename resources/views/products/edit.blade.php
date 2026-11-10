<x-app-layout>
    <x-slot name="title">{{ $product->name }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Edit :name', ['name' => $product->name])">
            <x-slot name="actions">
                <x-link-button variant="secondary" :href="route('manual-deductions.create', ['product' => $product->id])">{{ __('Deduct this product') }}</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php($money = fn ($v) => \App\Support\Money::format($v))

    <div x-data="productForm(@js($formState))" class="grid gap-6 pb-24 lg:grid-cols-3 lg:items-start lg:pb-0">
        <x-card class="lg:col-span-2">
            <form method="POST" action="{{ route('products.update', $product) }}" class="flex flex-col gap-8">
                @csrf
                @method('PUT')
                @include('products._form')

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                    <x-link-button variant="secondary" :href="route('products.index')">{{ __('Back to products') }}</x-link-button>
                </div>
            </form>
        </x-card>

        <aside class="space-y-6 lg:sticky lg:top-24">
            @include('products._cost-panel')

            {{-- The saved recipe, every level flattened to inventory items. --}}
            <x-card :title="__('One unit deducts')">
                <x-slot name="actions">
                    <x-badge :color="$metrics->status->color()">{{ $metrics->status->label() }}</x-badge>
                </x-slot>

                @if ($metrics->error)
                    <p class="text-sm text-red-700 dark:text-red-400">{{ $metrics->error }}</p>
                @else
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">{{ __('Saved recipe, every level added up, including products inside this one.') }}</p>
                    <ul class="space-y-1 text-sm">
                        @foreach ($metrics->costBreakdown as $line)
                            <li class="flex justify-between gap-3">
                                <span class="text-gray-800 dark:text-gray-200">{{ \App\Support\Quantity::format($line['quantity'], $line['unit']) }} {{ $line['name'] }}</span>
                                @if ($line['cost'] === null)
                                    <span class="whitespace-nowrap text-xs text-yellow-700 dark:text-yellow-400">{{ __('no cost') }}</span>
                                @else
                                    <span class="whitespace-nowrap tabular-nums text-gray-600 dark:text-gray-400">{{ $money($line['cost']) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 flex justify-between gap-3 border-t border-gray-100 pt-3 text-sm dark:border-gray-800">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Cost') }}</span>
                        <span class="font-medium tabular-nums text-gray-900 dark:text-gray-100">{{ $money($metrics->cost) }}@if ($metrics->missingCostItems)<span class="text-yellow-700 dark:text-yellow-400">+</span>@endif</span>
                    </p>
                @endif
            </x-card>

            @if ($product->usedIn->isNotEmpty())
                <x-card :title="__('Inside these products')">
                    <ul class="space-y-1 text-sm">
                        @foreach ($product->usedIn as $parent)
                            <li>
                                <a href="{{ route('products.edit', $parent) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">{{ $parent->name }}</a>
                                <span class="text-gray-500 dark:text-gray-400">({{ \App\Support\Quantity::format($parent->pivot->quantity) }} ×)</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card :title="__('Delete product')">
                <form method="POST" action="{{ route('products.destroy', $product) }}"
                      onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Delete :name? Orders containing its SKU will fail until it is registered again.', ['name' => $product->name])) }})"
                      class="space-y-3">
                    @csrf
                    @method('DELETE')
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Removes the product and its recipe. Stock already deducted is not affected. Products inside a combo can\'t be deleted.') }}</p>
                    <x-danger-button>{{ __('Delete') }}</x-danger-button>
                </form>
            </x-card>
        </aside>
    </div>
</x-app-layout>
