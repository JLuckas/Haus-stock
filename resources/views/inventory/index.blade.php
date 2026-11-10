<x-app-layout>
    <x-slot name="title">{{ __('Inventory') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Inventory')" :subtitle="__('Ingredients and resale items. Stock goes down automatically as Saipos orders arrive.')">
            <x-slot name="actions">
                <x-link-button :href="route('inventory.create')">{{ __('Add item') }}</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="GET" class="grid gap-3 sm:grid-cols-[1fr_auto] md:flex md:flex-row md:items-center">
        <div class="sm:col-span-2 md:flex-1">
            <x-input-label for="search" :value="__('Search')" class="sr-only" />
            <x-text-input id="search" name="search" type="search" :placeholder="__('Search by name…')" class="w-full" :value="$filters['search'] ?? ''" />
        </div>
        <div>
            <x-input-label for="type" :value="__('Type')" class="sr-only" />
            <x-select id="type" name="type" class="w-full md:w-auto">
                <option value="">{{ __('All types') }}</option>
                @foreach (\App\Enums\InventoryItemType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-select>
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="low" value="1" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-950 dark:checked:bg-indigo-500 dark:focus:ring-offset-gray-900" @checked($filters['low'] ?? false)>
            {{ __('Below minimum only') }}
        </label>
        <div class="flex gap-2 sm:col-span-2 md:col-span-1">
            <x-primary-button class="flex-1 md:flex-none">{{ __('Filter') }}</x-primary-button>
            @if (array_filter($filters))
                <x-link-button variant="secondary" :href="route('inventory.index')" class="flex-1 md:flex-none">{{ __('Clear') }}</x-link-button>
            @endif
        </div>
    </form>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="table-stack min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                <thead class="bg-gray-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800/40 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3 sm:px-6">{{ __('Item') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('In stock') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('Minimum') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('Cost') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 sm:px-6"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($items as $item)
                        @php($low = $item->isBelowMinimum())
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 sm:px-6">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $item->name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->type->label() }}</div>
                            </td>
                            <td data-label="{{ __('In stock') }}" class="px-4 py-3 text-right tabular-nums whitespace-nowrap">
                                <div>
                                    <div @class(['font-medium', 'text-red-600 dark:text-red-400' => $low, 'text-gray-900 dark:text-gray-100' => ! $low])>{{ $item->formatQuantity($item->current_stock) }}</div>
                                    @if ($item->isScaledQuantity($item->current_stock))
                                        {{-- Exact amount in the stored unit, e.g. "19,43 kg" over "19.430 g" --}}
                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ $item->exactQuantity($item->current_stock) }}</div>
                                    @endif
                                </div>
                            </td>
                            <td data-label="{{ __('Minimum') }}" class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-gray-500 dark:text-gray-400"
                                @if ($item->isScaledQuantity($item->minimum_stock)) title="{{ $item->exactQuantity($item->minimum_stock) }}" @endif>
                                {{ $item->formatQuantity($item->minimum_stock) }}
                            </td>
                            <td data-label="{{ __('Cost') }}" class="px-4 py-3 text-right tabular-nums whitespace-nowrap">
                                @if ($item->hasCost())
                                    <span class="text-gray-700 dark:text-gray-300">{{ $item->formattedCost() }}</span>
                                @else
                                    <a href="{{ route('inventory.edit', $item) }}" class="text-xs font-medium text-yellow-700 dark:text-yellow-400 hover:underline">{{ __('Set cost') }}</a>
                                @endif
                            </td>
                            <td data-label="{{ __('Status') }}" class="px-4 py-3">
                                @if ((float) $item->current_stock < 0)
                                    <x-badge color="red">{{ __('Negative') }}</x-badge>
                                @elseif ($low)
                                    <x-badge color="yellow">{{ __('Low') }}</x-badge>
                                @else
                                    <x-badge color="green">{{ __('OK') }}</x-badge>
                                @endif
                            </td>
                            <td class="stack-actions px-4 py-3 sm:px-6">
                                <div class="flex justify-end gap-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('inventory.receive', $item) }}" class="py-1 text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Receive') }}</a>
                                    <a href="{{ route('manual-deductions.create', ['item' => $item->id]) }}" class="py-1 text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Deduct') }}</a>
                                    <a href="{{ route('inventory.edit', $item) }}" class="py-1 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">{{ __('Edit') }}</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                {{ __('No inventory items match these filters.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{ $items->links() }}
</x-app-layout>
