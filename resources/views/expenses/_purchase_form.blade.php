{{--
    Supplier purchase fields (inside a <form>). State: purchaseForm in resources/js/purchase-form.js.
    A received purchase shows its items read-only: undo the stock entry (edit page) to change them.
--}}
@php
    $received = $purchase->exists && $purchase->isReceived();
    $selectedTags = old('tags', $purchase->relationLoaded('tags') ? $purchase->tags->pluck('name')->all() : []);
    $trash = '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>';
@endphp

<div class="space-y-5" x-data="purchaseForm(@js($purchaseState))">
    @if ($suppliers->isEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300">
            {{ __('Add your first supplier to register purchases.') }}
            <a href="{{ route('expenses.suppliers.index') }}" class="font-medium underline">{{ __('Manage suppliers') }}</a>
        </div>
    @endif

    <x-field :label="__('Supplier')" for="supplier_id" error="supplier_id">
        <x-select id="supplier_id" name="supplier_id" class="block w-full" x-model="supplierId" x-on:change="supplierChanged()" required>
            <option value="">{{ __('Choose a supplier…') }}</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
            @endforeach
        </x-select>
    </x-field>

    <x-field :label="__('Date')" for="spent_on" error="spent_on">
        <x-text-input id="spent_on" name="spent_on" type="date" class="block w-full" :value="old('spent_on', $purchase->spent_on?->toDateString() ?? today()->toDateString())" required />
    </x-field>

    {{-- What was bought --}}
    <div>
        <p class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Items bought') }}</p>

        @if ($received)
            <ul class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200 text-sm dark:divide-gray-800 dark:border-gray-800">
                @foreach ($purchase->items as $line)
                    <li class="flex items-center justify-between gap-3 px-3 py-2">
                        <span class="text-gray-800 dark:text-gray-200">{{ $line->inventoryItem->formatQuantity($line->quantity) }} {{ $line->inventoryItem->name }}</span>
                        <span class="tabular-nums text-gray-600 dark:text-gray-400">{{ \App\Support\Money::format($line->total) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('These items are already in stock. To change them, undo the stock entry first.') }}</p>
        @else
            <x-input-error :messages="$errors->get('items')" class="mt-1" />
            <div class="mt-2 space-y-3">
                <template x-for="(line, index) in lines" :key="line._key">
                    <div class="rounded-lg border border-gray-200 p-2.5 dark:border-gray-800" x-effect="autoTotal(line)">
                        <div class="flex items-start gap-2">
                            <x-select class="block w-full min-w-0 flex-1" x-model="line.inventory_item_id" ::name="`items[${index}][inventory_item_id]`" :aria-label="__('Item')">
                                <option value="">{{ __('Choose an item…') }}</option>
                                <template x-for="(info, id) in items" :key="id">
                                    <option :value="id" x-text="info.name" :selected="String(id) === String(line.inventory_item_id)"></option>
                                </template>
                            </x-select>
                            <button type="button" class="p-2 text-gray-400 hover:text-red-600 dark:text-gray-500" x-on:click="remove(index)" :aria-label="@js(__('Remove item'))">{!! $trash !!}</button>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <div class="flex">
                                <x-decimal-input class="block w-full min-w-0 rounded-r-none" :placeholder="__('Qty')" x-model="line.quantity" bind-name="`items[${index}][quantity]`" :aria-label="__('Quantity')" />
                                <span class="inline-flex w-10 shrink-0 items-center justify-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-50 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400" x-text="unit(line)"></span>
                            </div>
                            <div class="flex" x-on:input="line.touched = true">
                                <span class="inline-flex shrink-0 items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-2 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">R$</span>
                                <x-decimal-input class="block w-full min-w-0 rounded-l-none" decimals="2" fixed="2" :placeholder="__('Total')" x-model="line.total" bind-name="`items[${index}][total]`" :aria-label="__('Line total')" />
                            </div>
                        </div>
                        <p class="mt-1.5 flex flex-wrap justify-between gap-x-3 text-xs text-gray-500 dark:text-gray-400" x-show="unitPrice(line) || stockAfter(line)">
                            <span x-text="unitPrice(line)"></span>
                            <span x-show="stockAfter(line)">{{ __('Stock after:') }} <span class="font-medium text-gray-700 dark:text-gray-300" x-text="stockAfter(line)"></span></span>
                        </p>
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="error(index, 'inventory_item_id', 'quantity', 'total')" x-text="error(index, 'inventory_item_id', 'quantity', 'total')"></p>
                    </div>
                </template>
            </div>
            <div class="mt-3 flex items-center justify-between gap-3">
                <x-secondary-button class="h-8 px-3 text-xs" x-on:click="add()">+ {{ __('Add item') }}</x-secondary-button>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Total') }} <span class="ml-1 text-lg font-semibold tabular-nums text-gray-900 dark:text-white" x-text="money(total)">R$ 0,00</span></p>
            </div>
        @endif
    </div>

    <x-field :label="__('Invoice or description (optional)')" for="description" error="description">
        <x-text-input id="description" name="description" class="block w-full" maxlength="255" :value="old('description', $purchase->description)" :placeholder="__('e.g. NF 1234')" />
    </x-field>

    @unless ($purchase->exists)
        <div class="space-y-2 rounded-xl border border-indigo-200 bg-indigo-50/60 p-3 dark:border-indigo-500/30 dark:bg-indigo-500/10">
            <input type="hidden" name="receive_now" value="0">
            <label class="flex items-start gap-2.5 text-sm">
                <input type="checkbox" name="receive_now" value="1" @checked(old('receive_now', true)) class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-950">
                <span><span class="font-medium text-gray-900 dark:text-gray-100">{{ __('The goods arrived: add them to stock now') }}</span>
                    <span class="block text-xs text-gray-600 dark:text-gray-400">{{ __('Leave it off for an order still on the way and activate it when it arrives.') }}</span></span>
            </label>
            <input type="hidden" name="update_costs" value="0">
            <label class="flex items-start gap-2.5 text-sm">
                <input type="checkbox" name="update_costs" value="1" @checked(old('update_costs', true)) class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-950">
                <span><span class="font-medium text-gray-900 dark:text-gray-100">{{ __('Use these prices as the items\' cost') }}</span>
                    <span class="block text-xs text-gray-600 dark:text-gray-400">{{ __('Keeps recipe costs and margins up to date.') }}</span></span>
            </label>
        </div>
    @endunless

    <x-field :label="__('Tags')" for="tags-input" error="tags">
        <x-tag-input id="tags-input" :selected="$selectedTags" :suggestions="$tagSuggestions" :manage-url="route('expenses.tags.index')" />
    </x-field>

    <x-field :label="__('Notes (optional)')" for="notes" error="notes">
        <x-textarea id="notes" name="notes" rows="2" maxlength="1000">{{ old('notes', $purchase->notes) }}</x-textarea>
    </x-field>
</div>
