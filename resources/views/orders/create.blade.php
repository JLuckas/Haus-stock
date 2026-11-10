<x-app-layout>
    <x-slot name="title">{{ __('New order') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('New order')"
                       :subtitle="__('Register a sale by hand, e.g. outside Saipos or while the sync is down. Stock is deducted as soon as you save.')" />
    </x-slot>

    @php
        $productInfo = $products->mapWithKeys(fn ($p) => [$p->id => ['name' => $p->name, 'price' => (float) $p->price]]);
        $initialLines = old('items') ?: [['product_id' => '', 'quantity' => '1']];
        $trashIcon = '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>';
    @endphp

    <form method="POST" action="{{ route('orders.store') }}" class="grid gap-6 lg:grid-cols-3 lg:items-start"
          x-data="{
            lines: @js(array_values($initialLines)).map((line, i) => ({ ...line, _key: i })),
            nextKey: 1000,
            products: @js($productInfo),
            breakdowns: @js($breakdowns),
            errors: @js($errors->getMessages()),
            money(value) { return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) },
            add() { this.lines.push({ product_id: '', quantity: '1', _key: this.nextKey++ }) },
            remove(index) { this.lines.splice(index, 1); if (this.lines.length === 0) this.add() },
            qty(line) { return parseFloat(line.quantity) || 0 },
            subtotal(line) { return (this.products[line.product_id]?.price ?? 0) * this.qty(line) },
            get total() { return this.lines.reduce((sum, line) => sum + this.subtotal(line), 0) },
            get preview() {
                const totals = {};
                for (const line of this.lines) {
                    for (const part of this.breakdowns[line.product_id]?.lines ?? []) {
                        const key = part.name + '|' + part.unit;
                        totals[key] ??= { name: part.name, unit: part.unit, qty: 0 };
                        totals[key].qty += part.qty * this.qty(line);
                    }
                }
                return Object.values(totals).filter(t => t.qty > 0).sort((a, b) => a.name.localeCompare(b.name));
            },
            problem(index, line) {
                return [(this.errors[`items.${index}.product_id`] || [])[0], (this.errors[`items.${index}.quantity`] || [])[0], this.breakdowns[line.product_id]?.error]
                    .filter(Boolean).join(' ');
            },
          }">
        @csrf

        <x-card class="lg:col-span-2">
            <div class="space-y-8">
                <div>
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Products') }}</h3>
                    <x-input-error :messages="$errors->get('items')" class="mt-2" />

                    <div class="mt-3 space-y-3">
                        <template x-for="(line, index) in lines" :key="line._key">
                            <div>
                                {{-- Phones: product on its own line, quantity / subtotal / remove below. --}}
                                <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
                                    <x-select class="block w-full sm:w-auto sm:min-w-0 sm:flex-1" x-model="line.product_id" ::name="`items[${index}][product_id]`" :aria-label="__('Product')">
                                        <option value="">{{ __('Choose a product…') }}</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }} — {{ \App\Support\Money::format($product->price) }}</option>
                                        @endforeach
                                    </x-select>
                                    <div class="flex w-40 items-stretch sm:w-44">
                                        <x-decimal-input step="1" min="1" class="block w-full min-w-0" x-model="line.quantity" bind-name="`items[${index}][quantity]`" :aria-label="__('Quantity')" />
                                        <span class="inline-flex w-10 shrink-0 items-center justify-center rounded-r-lg border border-l-0 border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 text-sm text-gray-600 dark:text-gray-400">×</span>
                                    </div>
                                    <span class="flex-1 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300 sm:w-28 sm:flex-none" x-text="money(subtotal(line))"></span>
                                    <button type="button" class="p-2 text-gray-400 dark:text-gray-500 hover:text-red-600" x-on:click="remove(index)" :aria-label="@js(__('Remove product'))">{!! $trashIcon !!}</button>
                                </div>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="problem(index, line)" x-text="problem(index, line)"></p>
                            </div>
                        </template>
                    </div>

                    <x-secondary-button class="mt-3" x-on:click="add()">+ {{ __('Add a product') }}</x-secondary-button>
                </div>

                <div class="grid gap-6 border-t border-gray-100 dark:border-gray-800 pt-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="reference" :value="__('Saipos order number (optional)')" />
                        <x-text-input id="reference" name="reference" type="text" maxlength="100" class="mt-1 block w-full font-mono" :value="old('reference')" autocomplete="off" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('If this sale is also in Saipos, type its number: the sync will recognize it and not deduct it twice. Leave empty to generate a reference.') }}</p>
                        <x-input-error :messages="$errors->get('reference')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="notes" :value="__('Notes (optional)')" />
                        <textarea id="notes" name="notes" rows="3" maxlength="1000"
                                  class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm dark:shadow-none focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-950 dark:text-gray-100">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>
                </div>
            </div>
        </x-card>

        <div class="space-y-6 lg:sticky lg:top-24">
            <x-card :title="__('Summary')">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total') }}</span>
                    <span class="text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="money(total)"></span>
                </div>

                <div class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-4 text-sm">
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ __('This will deduct') }}</p>
                    <ul class="mt-2 space-y-1" x-show="preview.length">
                        <template x-for="part in preview" :key="part.name + part.unit">
                            <li class="flex justify-between gap-3">
                                <span class="text-gray-700 dark:text-gray-300" x-text="part.name"></span>
                                <span class="whitespace-nowrap tabular-nums text-gray-900 dark:text-gray-100" x-text="formatQuantity(part.qty, part.unit)"></span>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-2 text-gray-500 dark:text-gray-400" x-show="! preview.length">{{ __('Choose products to see what will be deducted.') }}</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400" x-show="preview.length" x-cloak>{{ __('Includes the recipes of any products inside it.') }}</p>
                </div>

                <div class="mt-6 flex flex-col gap-2">
                    <x-primary-button class="w-full">{{ __('Register order') }}</x-primary-button>
                    <x-link-button variant="secondary" :href="route('orders.index')" class="w-full">{{ __('Cancel') }}</x-link-button>
                </div>
            </x-card>
        </div>
    </form>
</x-app-layout>
