<x-app-layout>
    <x-slot name="title">{{ __('New manual deduction') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('New manual deduction')" :subtitle="__('Remove stock that left without a sale. Each deduction is logged with your name.')" />
    </x-slot>

    @php
        $itemInfo = $inventory->mapWithKeys(fn ($i) => [$i->id => [
            'unit' => $i->unit_of_measure->value,
            'stock' => $i->formatQuantity($i->current_stock),
            'current' => (float) $i->current_stock,
        ]]);
        $initialTarget = old('target', $selectedProductId ? 'product' : 'item');
    @endphp

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('manual-deductions.store') }}" class="flex flex-col gap-6"
              x-data="{
                target: @js($initialTarget),
                itemId: @js((string) old('inventory_item_id', $selectedItemId ?? '')),
                productId: @js((string) old('product_id', $selectedProductId ?? '')),
                quantity: @js((string) old('quantity', $initialTarget === 'product' ? '1' : '')),
                reason: @js(old('reason', '')),
                info: @js($itemInfo),
                breakdowns: @js($productBreakdowns),
                get breakdown() { return this.breakdowns[this.productId] ?? null },
                get item() { return this.info[this.itemId] ?? null },
                get preview() {
                    const qty = parseFloat(this.quantity) || 0;
                    return (this.breakdown?.lines ?? []).map(l => `${formatQuantity(l.qty * qty, l.unit)} ${l.name}`);
                },
              }">
            @csrf

            <fieldset>
                <legend class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('What are you deducting?') }}</legend>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer gap-3 rounded-lg border p-3" :class="target === 'item' ? 'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50/40 dark:bg-indigo-500/10' : 'border-gray-200 dark:border-gray-800'">
                        <input type="radio" name="target" value="item" x-model="target" class="mt-0.5 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-950 dark:checked:bg-indigo-500 dark:focus:ring-offset-gray-900">
                        <span>
                            <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('An inventory item') }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('e.g. 2 buns, 150 g of bacon') }}</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer gap-3 rounded-lg border p-3" :class="target === 'product' ? 'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50/40 dark:bg-indigo-500/10' : 'border-gray-200 dark:border-gray-800'">
                        <input type="radio" name="target" value="product" x-model="target" class="mt-0.5 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-950 dark:checked:bg-indigo-500 dark:focus:ring-offset-gray-900">
                        <span>
                            <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('A product') }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('e.g. 1 burger or 1 combo; its whole recipe is deducted') }}</span>
                        </span>
                    </label>
                </div>
            </fieldset>

            <div x-show="target === 'item'" x-cloak>
                <x-input-label for="inventory_item_id" :value="__('Item')" />
                <x-select id="inventory_item_id" name="inventory_item_id" class="mt-1 block w-full" x-model="itemId" ::disabled="target !== 'item'">
                    <option value="">{{ __('Choose an item…') }}</option>
                    @foreach ($inventory as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </x-select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="info[itemId]">
                    {{ __('In stock:') }} <span class="font-medium text-gray-700 dark:text-gray-300" x-text="info[itemId]?.stock"></span>
                </p>
                <x-input-error :messages="$errors->get('inventory_item_id')" class="mt-2" />
            </div>

            <div x-show="target === 'product'" x-cloak>
                <x-input-label for="product_id" :value="__('Product')" />
                <x-select id="product_id" name="product_id" class="mt-1 block w-full" x-model="productId" ::disabled="target !== 'product'">
                    <option value="">{{ __('Choose a product…') }}</option>
                    @foreach ($products as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </x-select>
                <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="quantity" x-text="target === 'product' ? @js(__('How many')) : @js(__('Quantity'))" :value="__('Quantity')" />
                <div class="mt-1 flex items-stretch">
                    <x-decimal-input id="quantity" name="quantity" step="1" min="0" class="block w-full min-w-0" x-model="quantity" required />
                    <span class="inline-flex w-14 shrink-0 items-center justify-center rounded-r-lg border border-l-0 border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 text-sm text-gray-600 dark:text-gray-400"
                          x-text="target === 'product' ? '×' : (info[itemId]?.unit ?? '–')">–</span>
                </div>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" x-show="target === 'item' && item && Number(quantity) > 0" x-cloak>
                    <span x-show="isScaledQuantity(quantity, item?.unit)">= <span class="font-medium" x-text="formatQuantity(quantity, item?.unit)"></span> · </span>
                    {{ __('Stock after:') }} <span class="font-medium" x-text="formatQuantity((item?.current ?? 0) - Number(quantity), item?.unit)"></span>
                </p>
                <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
            </div>

            <div x-show="target === 'product' && breakdown" x-cloak class="rounded-md bg-gray-50 dark:bg-gray-800/60 p-4 text-sm ring-1 ring-gray-200 dark:ring-gray-800">
                <template x-if="breakdown?.error">
                    <p class="text-red-700 dark:text-red-400" x-text="breakdown.error"></p>
                </template>
                <template x-if="breakdown && ! breakdown.error">
                    <div>
                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ __('This will deduct') }}</p>
                        <ul class="mt-2 space-y-0.5 text-gray-700 dark:text-gray-300">
                            <template x-for="line in preview" :key="line">
                                <li x-text="line"></li>
                            </template>
                        </ul>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Includes the recipes of any products inside it.') }}</p>
                    </div>
                </template>
            </div>

            <div>
                <x-input-label for="reason" :value="__('Reason')" />
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($reasons as $suggestion)
                        <button type="button" x-on:click="reason = @js($suggestion)"
                                class="rounded-full px-3 py-1.5 text-xs font-medium ring-1 transition"
                                :class="reason === @js($suggestion) ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 ring-gray-300 dark:ring-gray-700 hover:bg-gray-50/70 dark:hover:bg-gray-800/40'">
                            {{ $suggestion }}
                        </button>
                    @endforeach
                </div>
                <x-text-input id="reason" name="reason" type="text" maxlength="255" class="mt-2 block w-full" :placeholder="__('Or type a reason…')" x-model="reason" required />
                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                <x-primary-button>{{ __('Deduct stock') }}</x-primary-button>
                <x-link-button variant="secondary" :href="route('manual-deductions.index')">{{ __('Cancel') }}</x-link-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
