<x-app-layout>
    <x-slot name="title">{{ __('Manual deductions') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Manual deductions')" :subtitle="__('Stock removed outside of sales, such as employee meals and waste.')">
            <x-slot name="actions">
                <x-link-button :href="route('manual-deductions.create')">{{ __('New deduction') }}</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div>
            <x-input-label for="item" :value="__('Item')" class="sr-only" />
            <x-select id="item" name="item" class="w-full sm:w-72">
                <option value="">{{ __('All items') }}</option>
                @foreach ($inventory as $option)
                    <option value="{{ $option->id }}" @selected((string) ($filters['item'] ?? '') === (string) $option->id)>{{ $option->name }}</option>
                @endforeach
            </x-select>
        </div>
        <div class="flex gap-2">
            <x-primary-button class="flex-1 sm:flex-none">{{ __('Filter') }}</x-primary-button>
            @if (array_filter($filters))
                <x-link-button variant="secondary" :href="route('manual-deductions.index')" class="flex-1 sm:flex-none">{{ __('Clear') }}</x-link-button>
            @endif
        </div>
    </form>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="table-stack min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                <thead class="bg-gray-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800/40 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3 sm:px-6">{{ __('Item') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('Quantity') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Reason') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('By') }}</th>
                        <th scope="col" class="px-4 py-3 sm:px-6">{{ __('When') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($deductions as $deduction)
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/40 align-top">
                            <td class="px-4 py-3 sm:px-6">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $deduction->targetName() }}</span>
                                @if ($deduction->isProductDeduction())
                                    <x-badge class="ml-1">{{ __('Product') }}</x-badge>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $deduction->lines->map(fn ($line) => $line->inventoryItem->formatQuantity($line->quantity).' '.$line->inventoryItem->displayName())->join(' · ') }}
                                    </p>
                                @endif
                            </td>
                            <td data-label="{{ __('Quantity') }}" class="px-4 py-3 text-right tabular-nums whitespace-nowrap">−{{ $deduction->formattedQuantity() }}</td>
                            <td data-label="{{ __('Reason') }}" class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ __($deduction->reason) }}</td>
                            <td data-label="{{ __('By') }}" class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $deduction->user?->name ?? __('Command line') }}</td>
                            <td data-label="{{ __('When') }}" class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400 sm:px-6">{{ $deduction->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">{{ __('No manual deductions recorded.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{ $deductions->links() }}
</x-app-layout>
