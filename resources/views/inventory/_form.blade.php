@php
    $editing = $item->exists;
    $costUnits = collect(\App\Enums\UnitOfMeasure::cases())->mapWithKeys(fn ($u) => [$u->value => $u->costDisplayUnit()]);
@endphp

{{-- Amounts are typed in the stored unit; the hints show them adaptively (e.g. 19430 g = 19,43 kg). --}}
<div class="grid gap-6 sm:grid-cols-2"
     x-data="{
        unit: @js(old('unit_of_measure', $item->unit_of_measure?->value ?? \App\Enums\UnitOfMeasure::cases()[0]->value)),
        opening: @js((string) old('current_stock', \App\Support\Quantity::forInput($item->current_stock))),
        minimum: @js((string) old('minimum_stock', \App\Support\Quantity::forInput($item->minimum_stock))),
        costUnits: @js($costUnits),
        labels: {
            opening: @js(__('Opening stock (in :unit)')),
            minimum: @js(__('Minimum stock (in :unit)')),
            cost: @js(__('Cost (R$ per :unit)')),
        },
        label(key, unit) { return this.labels[key].replace(':unit', unit) },
     }">
    <div class="sm:col-span-2">
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $item->name)" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="type" :value="__('Type')" />
        <x-select id="type" name="type" class="mt-1 block w-full" required>
            @foreach (\App\Enums\InventoryItemType::cases() as $type)
                <option value="{{ $type->value }}" @selected(old('type', $item->type?->value) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-select>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Ingredients are used in recipes. Standalone products, such as a 2L soda, are sold as they are.') }}</p>
        <x-input-error :messages="$errors->get('type')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="unit_of_measure" :value="__('Unit')" />
        <x-select id="unit_of_measure" name="unit_of_measure" class="mt-1 block w-full" x-model="unit" required>
            @foreach (\App\Enums\UnitOfMeasure::cases() as $unit)
                <option value="{{ $unit->value }}" @selected(old('unit_of_measure', $item->unit_of_measure?->value) === $unit->value)>{{ $unit->label() }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('unit_of_measure')" class="mt-2" />
    </div>

    <div>
        @if ($editing)
            <x-input-label :value="__('Current stock')" />
            <p class="mt-1 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item->formatQuantity($item->current_stock) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('To change it, use') }}
                <a class="text-indigo-600 dark:text-indigo-400 hover:underline" href="{{ route('inventory.receive', $item) }}">{{ __('Receive stock') }}</a>
                {{ __('or a') }}
                <a class="text-indigo-600 dark:text-indigo-400 hover:underline" href="{{ route('manual-deductions.create', ['item' => $item->id]) }}">{{ __('deduction') }}</a>.
            </p>
        @else
            <x-input-label for="current_stock" x-text="label('opening', unit)" :value="__('Opening stock')" />
            <div class="mt-1 flex"><x-decimal-input id="current_stock" name="current_stock" class="block w-full" x-model="opening" required /></div>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" x-show="isScaledQuantity(opening, unit)" x-cloak>= <span class="font-medium" x-text="formatQuantity(opening, unit)"></span></p>
            <x-input-error :messages="$errors->get('current_stock')" class="mt-2" />
        @endif
    </div>

    <div>
        <x-input-label for="minimum_stock" x-text="label('minimum', unit)" :value="__('Minimum stock')" />
        <div class="mt-1 flex"><x-decimal-input id="minimum_stock" name="minimum_stock" class="block w-full" x-model="minimum" required /></div>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" x-show="isScaledQuantity(minimum, unit)" x-cloak>= <span class="font-medium" x-text="formatQuantity(minimum, unit)"></span></p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('The item is flagged as low when stock drops below this amount.') }}</p>
        <x-input-error :messages="$errors->get('minimum_stock')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="cost" x-text="label('cost', costUnits[unit])" :value="__('Cost')" />
        <div class="mt-1 flex items-stretch">
            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 px-3 text-sm text-gray-600 dark:text-gray-400">R$</span>
            <x-decimal-input id="cost" name="cost" decimals="4" fixed="2" class="block w-full min-w-0 rounded-none"
                          :value="old('cost', $item->costPerDisplayUnit() !== null ? \App\Support\Quantity::forInput($item->costPerDisplayUnit()) : '')" :placeholder="__('Not set')" />
            <span class="inline-flex w-14 shrink-0 items-center justify-center rounded-r-lg border border-l-0 border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 text-sm text-gray-600 dark:text-gray-400" x-text="'/' + costUnits[unit]"></span>
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('What you pay the supplier. Used to calculate product cost and margin.') }}</p>
        <x-input-error :messages="$errors->get('cost')" class="mt-2" />
    </div>
</div>
