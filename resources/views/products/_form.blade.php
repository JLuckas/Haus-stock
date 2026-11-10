@php
    $recipeError = $errors->first('ingredients') ?: $errors->first('components');
    $trashIcon = '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>';
@endphp

{{-- Alpine state (productForm) lives on the page wrapper, shared with the live cost panel. --}}
<div class="space-y-8">

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <x-input-label for="saipos_sku_id" :value="__('Saipos SKU')" />
            <x-text-input id="saipos_sku_id" name="saipos_sku_id" type="text" class="mt-1 block w-full font-mono" :value="old('saipos_sku_id', $product->saipos_sku_id)" required />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('The item\'s integration code in Saipos.') }}</p>
            <x-input-error :messages="$errors->get('saipos_sku_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $product->name)" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="price" :value="__('Price (R$)')" />
            <div class="mt-1 flex"><x-decimal-input id="price" name="price" decimals="2" fixed="2" class="block w-full" x-model="price" required /></div>
            <x-input-error :messages="$errors->get('price')" class="mt-2" />
        </div>
    </div>

    {{-- Set in the cost panel; empty = the store's target margin. --}}
    <input type="hidden" name="target_margin_percent" :value="targetMargin">
    <x-input-error :messages="$errors->get('target_margin_percent')" />

    <fieldset>
        <legend class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('What does selling one unit take from stock?') }}</legend>
        <div class="mt-2 grid gap-3 sm:grid-cols-2">
            <label class="flex cursor-pointer gap-3 rounded-lg border p-4" :class="mode === 'recipe' ? 'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50/40 dark:bg-indigo-500/10' : 'border-gray-200 dark:border-gray-800'">
                <input type="radio" name="stock_mode" value="recipe" x-model="mode" class="mt-0.5 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-950 dark:checked:bg-indigo-500 dark:focus:ring-offset-gray-900">
                <span>
                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('A recipe') }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Ingredients and/or other products, e.g. a combo = 1 burger + 1 fries + 1 soda.') }}</span>
                </span>
            </label>
            <label class="flex cursor-pointer gap-3 rounded-lg border p-4" :class="mode === 'standalone' ? 'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50/40 dark:bg-indigo-500/10' : 'border-gray-200 dark:border-gray-800'">
                <input type="radio" name="stock_mode" value="standalone" x-model="mode" class="mt-0.5 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-950 dark:checked:bg-indigo-500 dark:focus:ring-offset-gray-900">
                <span>
                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('One standalone item') }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Sold as it is, e.g. a 2L Coke Zero takes 1 unit.') }}</span>
                </span>
            </label>
        </div>
        <x-input-error :messages="$errors->get('stock_mode')" class="mt-2" />
    </fieldset>

    <div x-show="mode === 'recipe'" x-cloak class="space-y-8">
        @if ($recipeError)
            <p class="rounded-lg bg-red-50 dark:bg-red-500/10 px-3 py-2 text-sm text-red-700 dark:text-red-400">{{ $recipeError }}</p>
        @endif

        {{-- Ingredients: inventory items consumed directly --}}
        <div>
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Ingredients') }} <span class="font-normal text-gray-500 dark:text-gray-400">({{ __('per unit sold') }})</span></h4>

            <div class="mt-3 space-y-3">
                <template x-for="(line, index) in ingredients" :key="line._key">
                    <div>
                        {{-- Phones: ingredient on its own line, quantity + remove below. --}}
                        <div class="flex flex-wrap items-start gap-2 sm:flex-nowrap">
                            <x-select class="block w-full sm:w-auto sm:min-w-0 sm:flex-1" x-model="line.inventory_item_id" ::name="`ingredients[${index}][inventory_item_id]`" ::disabled="mode !== 'recipe'" :aria-label="__('Ingredient')">
                                <option value="">{{ __('Choose an ingredient…') }}</option>
                                @foreach ($inventory as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </x-select>
                            <div class="flex flex-1 items-stretch sm:w-40 sm:flex-none">
                                <x-decimal-input class="block w-full min-w-0 rounded-r-none" :placeholder="__('Qty')" x-model="line.quantity_needed" bind-name="`ingredients[${index}][quantity_needed]`" bind-disabled="mode !== 'recipe'" :aria-label="__('Ingredient quantity')" />
                                <span class="inline-flex w-12 shrink-0 items-center justify-center rounded-r-lg border border-l-0 border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 text-sm text-gray-600 dark:text-gray-400" x-text="units[line.inventory_item_id] || '–'"></span>
                            </div>
                            <button type="button" class="p-2 text-gray-400 dark:text-gray-500 hover:text-red-600" x-on:click="remove('ingredients', index)" :aria-label="@js(__('Remove ingredient'))">{!! $trashIcon !!}</button>
                        </div>
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="error('ingredients', index, 'inventory_item_id', 'quantity_needed')"
                           x-text="error('ingredients', index, 'inventory_item_id', 'quantity_needed')"></p>
                    </div>
                </template>
                <p class="text-sm text-gray-500 dark:text-gray-400" x-show="ingredients.length === 0">{{ __('No ingredients used directly.') }}</p>
            </div>

            <x-secondary-button class="mt-3" x-on:click="add('ingredients', { inventory_item_id: '', quantity_needed: '' })">+ {{ __('Add ingredient') }}</x-secondary-button>
        </div>

        {{-- Components: other products, whose own recipes are deducted as part of this one --}}
        <div class="border-t border-gray-100 dark:border-gray-800 pt-6">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Products inside this one') }}</h4>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('For combos. Each product\'s own recipe is deducted as part of this product, so a box with 1 burger deducts the burger\'s meat, bun and cheese.') }}
            </p>

            <div class="mt-3 space-y-3">
                <template x-for="(line, index) in components" :key="line._key">
                    <div>
                        <div class="flex flex-wrap items-start gap-2 sm:flex-nowrap">
                            <x-select class="block w-full sm:w-auto sm:min-w-0 sm:flex-1" x-model="line.product_id" ::name="`components[${index}][product_id]`" ::disabled="mode !== 'recipe'" :aria-label="__('Product inside')">
                                <option value="">{{ __('Choose a product…') }}</option>
                                @foreach ($componentOptions as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </x-select>
                            <div class="flex flex-1 items-stretch sm:w-40 sm:flex-none">
                                <x-decimal-input step="1" min="1" class="block w-full min-w-0" :placeholder="__('Qty')" x-model="line.quantity" bind-name="`components[${index}][quantity]`" bind-disabled="mode !== 'recipe'" :aria-label="__('Product quantity')" />
                                <span class="inline-flex w-12 shrink-0 items-center justify-center rounded-r-lg border border-l-0 border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 text-sm text-gray-600 dark:text-gray-400">×</span>
                            </div>
                            <button type="button" class="p-2 text-gray-400 dark:text-gray-500 hover:text-red-600" x-on:click="remove('components', index)" :aria-label="@js(__('Remove product'))">{!! $trashIcon !!}</button>
                        </div>
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="error('components', index, 'product_id', 'quantity')"
                           x-text="error('components', index, 'product_id', 'quantity')"></p>
                    </div>
                </template>
                <p class="text-sm text-gray-500 dark:text-gray-400" x-show="components.length === 0">{{ __('No products inside.') }}</p>
            </div>

            <x-secondary-button class="mt-3" x-on:click="add('components', { product_id: '', quantity: '1' })">+ {{ __('Add a product') }}</x-secondary-button>
        </div>
    </div>

    <div x-show="mode === 'standalone'" x-cloak>
        <x-input-label for="inventory_item_id" :value="__('Standalone inventory item')" />
        @if ($standaloneItems->isEmpty())
            <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-400">
                {{ __('There are no standalone items yet.') }}
                <a class="underline" href="{{ route('inventory.create') }}">{{ __('Create an inventory item of type "Standalone product" first.') }}</a>
            </p>
        @endif
        <x-select id="inventory_item_id" name="inventory_item_id" class="mt-1 block w-full sm:w-1/2" x-model="standaloneId" ::disabled="mode !== 'standalone'">
            <option value="">{{ __('Choose an item…') }}</option>
            @foreach ($standaloneItems as $option)
                <option value="{{ $option->id }}" @selected((string) old('inventory_item_id', $product->inventory_item_id) === (string) $option->id)>{{ $option->name }}</option>
            @endforeach
        </x-select>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Each unit sold deducts exactly 1 unit of this item.') }}</p>
        <x-input-error :messages="$errors->get('inventory_item_id')" class="mt-2" />
    </div>
</div>
