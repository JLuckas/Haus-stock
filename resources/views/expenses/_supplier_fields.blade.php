{{-- Supplier fields: name, phone, notes and the items they sell (searchable checklist). $supplier may be null (new). --}}
@php
    $chosen = collect(old('items', $supplier?->items->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $prefix = $supplier ? 'supplier-'.$supplier->id : 'new-supplier';
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <x-field :label="__('Name')" :for="$prefix.'-name'" error="name">
        <x-text-input :id="$prefix.'-name'" name="name" class="block w-full" maxlength="120" :value="old('name', $supplier?->name)" :placeholder="__('e.g. Padaria Pão Bom')" required />
    </x-field>
    <x-field :label="__('Phone (optional)')" :for="$prefix.'-phone'" error="phone">
        <x-text-input :id="$prefix.'-phone'" name="phone" class="block w-full" maxlength="40" :value="old('phone', $supplier?->phone)" inputmode="tel" />
    </x-field>
</div>

<fieldset x-data="{ q: '' }">
    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('What they sell') }}</legend>
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('A new purchase from this supplier starts with these items.') }}</p>
    <x-text-input type="search" x-model="q" class="mt-2 block w-full" :placeholder="__('Filter items…')" :aria-label="__('Filter items…')" />
    <div class="mt-2 max-h-48 space-y-0.5 overflow-y-auto rounded-lg border border-gray-200 p-1.5 dark:border-gray-800">
        @foreach ($inventory as $item)
            <label class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60"
                   x-show="q === '' || @js(mb_strtolower($item->name)).includes(q.toLowerCase())">
                <input type="checkbox" name="items[]" value="{{ $item->id }}" @checked(in_array($item->id, $chosen, true))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-950">
                <span class="text-gray-800 dark:text-gray-200">{{ $item->name }}</span>
                <span class="ml-auto text-xs text-gray-400">{{ $item->unit_of_measure->costDisplayUnit() }}</span>
            </label>
        @endforeach
    </div>
</fieldset>

<x-field :label="__('Notes (optional)')" :for="$prefix.'-notes'" error="notes">
    <x-textarea :id="$prefix.'-notes'" name="notes" rows="2" maxlength="500">{{ old('notes', $supplier?->notes) }}</x-textarea>
</x-field>
