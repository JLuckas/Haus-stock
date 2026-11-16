<x-app-layout>
    <x-slot name="title">{{ __('Edit purchase') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Purchase from :supplier', ['supplier' => $purchase->supplier->name])" :subtitle="$purchase->spent_on->format('d/m/Y').' · '.\App\Support\Money::format($purchase->amount)" />
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        <x-card class="lg:col-span-2">
            <form method="POST" action="{{ route('expenses.purchases.update', $purchase) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @include('expenses._purchase_form')

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                    <x-link-button variant="secondary" :href="route('expenses.index', 'supplier')">{{ __('Cancel') }}</x-link-button>
                </div>
            </form>
        </x-card>

        <aside class="space-y-6">
            <x-card :title="__('Stock')">
                @if ($purchase->isReceived())
                    <p class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <x-badge color="green" dot>{{ __('In stock') }}</x-badge>
                        {{ __('since :date', ['date' => $purchase->received_at->format('d/m/Y H:i')]) }}
                    </p>
                    <form method="POST" action="{{ route('expenses.purchases.unreceive', $purchase) }}" class="mt-4"
                          onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Take these items out of stock again, so you can correct the purchase?')) }})">
                        @csrf
                        <x-secondary-button type="submit" class="w-full">{{ __('Undo the stock entry') }}</x-secondary-button>
                    </form>
                @else
                    <p class="text-sm text-gray-700 dark:text-gray-300"><x-badge color="amber" dot>{{ __('Waiting for the goods') }}</x-badge></p>
                    <form method="POST" action="{{ route('expenses.purchases.receive', $purchase) }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="update_costs" value="0">
                        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="update_costs" value="1" checked class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-950">
                            {{ __('Use these prices as the items\' cost') }}
                        </label>
                        <x-primary-button class="w-full"><x-icon name="receive" class="h-4 w-4" />{{ __('Activate: add to stock') }}</x-primary-button>
                    </form>
                @endif
            </x-card>

            <x-card :title="__('Delete purchase')">
                <form method="POST" action="{{ route('expenses.purchases.destroy', $purchase) }}"
                      onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Delete this purchase? Items already in stock are taken out again.')) }})" class="space-y-3">
                    @csrf
                    @method('DELETE')
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('If it was added to stock, its items are taken out again.') }}</p>
                    <x-danger-button>{{ __('Delete') }}</x-danger-button>
                </form>
            </x-card>
        </aside>
    </div>
</x-app-layout>
