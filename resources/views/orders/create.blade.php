<x-app-layout>
    <x-slot name="title">{{ __('New order') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('New order')"
                       :subtitle="__('Register a sale by hand, with any changes the customer asked for (swap the cheese, no onion, another burger in the box…). Stock is deducted as soon as you save.')" />
    </x-slot>

    @php
        $trashIcon = '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>';
        $small = 'field-control h-8 py-0 text-xs';
    @endphp

    <form method="POST" action="{{ route('orders.store') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start" x-data="orderForm(@js($orderState))">
        @csrf

        <x-card class="lg:col-span-2">
            <div class="space-y-8">
                <div>
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Products') }}</h3>
                    <x-input-error :messages="$errors->get('items')" class="mt-2" />

                    <div class="mt-3 space-y-3">
                        <template x-for="(line, index) in lines" :key="line._key">
                            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800" :class="line.open && '!border-indigo-300 dark:!border-indigo-500/40'">
                                {{-- Product, quantity, subtotal --}}
                                <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
                                    <x-select class="block w-full sm:w-auto sm:min-w-0 sm:flex-1" x-model="line.product_id" x-on:change="productChanged(line)" ::name="`items[${index}][product_id]`" :aria-label="__('Product')">
                                        <option value="">{{ __('Choose a product…') }}</option>
                                        @foreach ($products->groupBy(fn ($p) => $p->category?->name ?? __('No category')) as $group => $groupProducts)
                                            <optgroup label="{{ $group }}">
                                                @foreach ($groupProducts as $product)
                                                    <option value="{{ $product->id }}">{{ $product->name }} — {{ \App\Support\Money::format($product->price) }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </x-select>
                                    <div class="flex w-36 items-stretch">
                                        <x-decimal-input step="1" min="1" class="block w-full min-w-0" x-model="line.quantity" bind-name="`items[${index}][quantity]`" :aria-label="__('Quantity')" />
                                    </div>
                                    <span class="flex-1 text-right text-sm font-medium tabular-nums text-gray-800 dark:text-gray-200 sm:w-24 sm:flex-none" x-text="money(subtotal(line))"></span>
                                    <button type="button" class="p-2 text-gray-400 hover:text-red-600 dark:text-gray-500" x-on:click="removeLine(index)" :aria-label="@js(__('Remove product'))">{!! $trashIcon !!}</button>
                                </div>

                                {{-- Summary of changes + toggle --}}
                                <div class="mt-2 flex flex-wrap items-center gap-1.5" x-show="plan(line)?.items">
                                    <button type="button" x-on:click="line.open = ! line.open" :aria-expanded="line.open.toString()"
                                            class="inline-flex items-center gap-1 rounded-full border border-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                        <x-icon name="products" class="h-3.5 w-3.5" />
                                        <span x-text="line.open ? @js(__('Hide changes')) : @js(__('Customize'))"></span>
                                    </button>
                                    <template x-for="(label, li) in describe(line)" :key="li + label">
                                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300" x-text="label"></span>
                                    </template>
                                </div>

                                {{-- What gets submitted for the changes --}}
                                <template x-for="(change, ci) in changes(line)" :key="ci + JSON.stringify(change)">
                                    <div class="hidden">
                                        <template x-for="(value, field) in change" :key="field">
                                            <input type="hidden" :name="`items[${index}][customizations][${ci}][${field}]`" :value="value">
                                        </template>
                                    </div>
                                </template>

                                {{-- Customization panel --}}
                                <div x-show="line.open && plan(line)?.items" x-cloak class="mt-3 space-y-4 border-t border-gray-100 pt-3 dark:border-gray-800">
                                    {{-- Products inside a box --}}
                                    <div x-show="components(line).length">
                                        <p class="eyebrow">{{ __('Inside it') }}</p>
                                        <div class="mt-2 space-y-1.5">
                                            <template x-for="component in components(line)" :key="component.id">
                                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                                    <span class="min-w-0 text-gray-800 dark:text-gray-200" :class="line.componentChoice[component.id] === 'remove' && 'line-through opacity-60'">
                                                        <span x-text="component.qty + ' × ' + component.name"></span>
                                                    </span>
                                                    <select class="{{ $small }} w-full sm:w-56" x-model="line.componentChoice[component.id]" :aria-label="@js(__('Change')) + ' ' + component.name">
                                                        <option value="">{{ __('Keep') }}</option>
                                                        <option value="remove">{{ __('Remove') }}</option>
                                                        <template x-if="swapProducts(component.id, line.product_id).length">
                                                            <optgroup label="{{ __('Swap for') }}">
                                                                <template x-for="option in swapProducts(component.id, line.product_id)" :key="option.id">
                                                                    <option :value="option.id" x-text="option.name" :selected="line.componentChoice[component.id] === option.id"></option>
                                                                </template>
                                                            </optgroup>
                                                        </template>
                                                    </select>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- More products in it (e.g. another burger in the box) --}}
                                    <div>
                                        <template x-for="(extra, ei) in line.added" :key="extra._key">
                                            <div class="mb-1.5 flex items-center gap-2">
                                                <select class="{{ $small }} min-w-0 flex-1" x-model="extra.product" :aria-label="@js(__('Product to add'))">
                                                    <option value="">{{ __('Choose a product…') }}</option>
                                                    <template x-for="option in addableProducts(line)" :key="option.id">
                                                        <option :value="option.id" x-text="option.name" :selected="extra.product === option.id"></option>
                                                    </template>
                                                </select>
                                                <input type="text" inputmode="decimal" class="{{ $small }} w-16 text-center" x-model="extra.quantity" :aria-label="@js(__('Quantity'))">
                                                <button type="button" class="p-1 text-gray-400 hover:text-red-600" x-on:click="line.added.splice(ei, 1)" :aria-label="@js(__('Remove'))">&times;</button>
                                            </div>
                                        </template>
                                        <button type="button" class="link text-xs" x-on:click="addProduct(line)">+ {{ __('Add a product to it (e.g. another burger)') }}</button>
                                    </div>

                                    {{-- Ingredients --}}
                                    <div>
                                        <p class="eyebrow">{{ __('Ingredients (per unit)') }}</p>
                                        <div class="mt-2 space-y-1.5">
                                            <template x-for="ingredient in ingredients(line)" :key="ingredient.id">
                                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                                    <span class="min-w-0 text-gray-800 dark:text-gray-200" :class="line.ingredientChoice[ingredient.id] === 'remove' && 'line-through opacity-60'">
                                                        <span class="tabular-nums text-gray-500 dark:text-gray-400" x-text="formatQuantity(ingredient.qty, ingredient.unit)"></span>
                                                        <span x-text="ingredient.name"></span>
                                                        <span class="ml-1 rounded-full bg-gray-100 px-1.5 text-[10px] text-gray-500 dark:bg-gray-800 dark:text-gray-400" x-show="categoryName(items[ingredient.id]?.category)" x-text="categoryName(items[ingredient.id]?.category)"></span>
                                                    </span>
                                                    <select class="{{ $small }} w-full sm:w-56" x-model="line.ingredientChoice[ingredient.id]" :aria-label="@js(__('Change')) + ' ' + ingredient.name">
                                                        <option value="">{{ __('Keep') }}</option>
                                                        <option value="remove">{{ __('Remove') }}</option>
                                                        <template x-if="swapItems(ingredient.id).length">
                                                            <optgroup label="{{ __('Swap for') }}">
                                                                <template x-for="option in swapItems(ingredient.id)" :key="option.id">
                                                                    <option :value="option.id" x-text="option.name" :selected="line.ingredientChoice[ingredient.id] === option.id"></option>
                                                                </template>
                                                            </optgroup>
                                                        </template>
                                                    </select>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Extras --}}
                                    <div>
                                        <template x-for="(extra, ei) in line.extras" :key="extra._key">
                                            <div class="mb-1.5 flex items-center gap-2">
                                                <select class="{{ $small }} min-w-0 flex-1" x-model="extra.item" :aria-label="@js(__('Extra ingredient'))">
                                                    <option value="">{{ __('Choose an ingredient…') }}</option>
                                                    @foreach (collect($orderState['items'])->groupBy(fn ($i) => $orderState['categories']->get($i['category'])?->name ?? __('No category'), preserveKeys: true) as $group => $groupItems)
                                                        <optgroup label="{{ $group }}">
                                                            @foreach ($groupItems as $id => $item)
                                                                <option value="{{ $id }}">{{ $item['name'] }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                                <input type="text" inputmode="decimal" class="{{ $small }} w-20 text-right" x-model="extra.quantity" :placeholder="@js(__('Qty'))" :aria-label="@js(__('Quantity'))">
                                                <span class="w-6 text-xs text-gray-500" x-text="items[extra.item]?.unit ?? ''"></span>
                                                <button type="button" class="p-1 text-gray-400 hover:text-red-600" x-on:click="line.extras.splice(ei, 1)" :aria-label="@js(__('Remove'))">&times;</button>
                                            </div>
                                        </template>
                                        <button type="button" class="link text-xs" x-on:click="addExtra(line)">+ {{ __('Add an extra ingredient (e.g. more bacon)') }}</button>
                                    </div>

                                    {{-- Price --}}
                                    <div class="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 text-sm dark:border-gray-800">
                                        <label class="text-gray-600 dark:text-gray-400" :for="`price-${line._key}`">{{ __('Price per unit') }}</label>
                                        <div class="flex w-36">
                                            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-2 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">R$</span>
                                            <input type="text" inputmode="decimal" :id="`price-${line._key}`" class="{{ $small }} w-full rounded-l-none text-right" x-model="line.unit_price"
                                                   :name="`items[${index}][unit_price]`" :placeholder="(products[line.product_id]?.price ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })">
                                        </div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Leave empty to charge the product\'s price; change it when extras are charged.') }}</span>
                                    </div>
                                </div>

                                <p class="mt-2 text-sm text-red-600 dark:text-red-400" x-show="problem(index, line)" x-text="problem(index, line)"></p>
                            </div>
                        </template>
                    </div>

                    <x-secondary-button class="mt-3" x-on:click="addLine()">+ {{ __('Add a product') }}</x-secondary-button>
                </div>

                <div class="grid gap-6 border-t border-gray-100 pt-6 dark:border-gray-800 sm:grid-cols-2">
                    <div>
                        <x-input-label for="reference" :value="__('Saipos order number (optional)')" />
                        <x-text-input id="reference" name="reference" type="text" maxlength="100" class="mt-1 block w-full font-mono" :value="old('reference')" autocomplete="off" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('If this sale is also in Saipos, type its number: the sync will recognize it and not deduct it twice. Leave empty to generate a reference.') }}</p>
                        <x-input-error :messages="$errors->get('reference')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="notes" :value="__('Notes (optional)')" />
                        <x-textarea id="notes" name="notes" rows="3" maxlength="1000" class="mt-1">{{ old('notes') }}</x-textarea>
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

                <div class="mt-4 border-t border-gray-100 pt-4 text-sm dark:border-gray-800">
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ __('This will deduct') }}</p>
                    <ul class="mt-2 space-y-1" x-show="preview.length">
                        <template x-for="part in preview" :key="part.id">
                            <li class="flex justify-between gap-3">
                                <span class="text-gray-700 dark:text-gray-300" x-text="part.name"></span>
                                <span class="whitespace-nowrap tabular-nums text-gray-900 dark:text-gray-100" x-text="formatQuantity(part.qty, part.unit)"></span>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-2 text-gray-500 dark:text-gray-400" x-show="! preview.length">{{ __('Choose products to see what will be deducted.') }}</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400" x-show="preview.length" x-cloak>{{ __('Includes the recipes of any products inside it and the changes asked for.') }}</p>
                </div>

                <div class="mt-6 flex flex-col gap-2">
                    <x-primary-button class="w-full">{{ __('Register order') }}</x-primary-button>
                    <x-link-button variant="secondary" :href="route('orders.index')" class="w-full">{{ __('Cancel') }}</x-link-button>
                </div>
            </x-card>
        </div>
    </form>
</x-app-layout>
