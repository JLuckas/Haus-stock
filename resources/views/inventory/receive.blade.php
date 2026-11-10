<x-app-layout>
    <x-slot name="title">{{ __('Receive stock') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Receive stock: :name', ['name' => $item->name])" :subtitle="__('Record goods that arrived from a supplier.')" />
    </x-slot>

    @php($unit = $item->unit_of_measure->value)

    <x-card class="max-w-xl">
        <dl class="mb-6 grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('In stock now') }}</dt>
                <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ $item->formatQuantity($item->current_stock) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Minimum') }}</dt>
                <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ $item->formatQuantity($item->minimum_stock) }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('inventory.receive.store', $item) }}" class="flex flex-col gap-6"
              x-data="{ quantity: @js((string) old('quantity', '')), current: {{ (float) $item->current_stock }}, unit: @js($unit) }">
            @csrf
            <div>
                <x-input-label for="quantity" :value="__('Quantity received (in :unit)', ['unit' => $unit])" />
                <div class="mt-1 flex items-stretch">
                    <x-decimal-input id="quantity" name="quantity" class="block w-full min-w-0 rounded-r-none" x-model="quantity" required autofocus />
                    <span class="inline-flex shrink-0 items-center rounded-r-lg border border-l-0 border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 px-3 text-sm text-gray-600 dark:text-gray-400">{{ $unit }}</span>
                </div>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" x-show="Number(quantity) > 0" x-cloak>
                    <span x-show="isScaledQuantity(quantity, unit)">= <span class="font-medium" x-text="formatQuantity(quantity, unit)"></span> · </span>
                    {{ __('Stock after:') }} <span class="font-medium" x-text="formatQuantity(current + Number(quantity), unit)"></span>
                </p>
                <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                <x-primary-button>{{ __('Add to stock') }}</x-primary-button>
                <x-link-button variant="secondary" :href="route('inventory.index')">{{ __('Cancel') }}</x-link-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
