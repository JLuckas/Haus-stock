<x-app-layout>
    @php
        $money = fn ($v) => \App\Support\Money::format($v);
    @endphp

    <x-slot name="title">{{ __('Suppliers') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Expenses')" :subtitle="__('The suppliers you buy stock from. A purchase from them adds the items to stock when it is activated.')">
            <x-slot name="actions">
                <x-link-button :href="route('expenses.index', 'supplier').'#expense-form'" icon="plus">{{ __('New purchase') }}</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('expenses._tabs', ['active' => 'suppliers'])

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        <x-card :title="__('New supplier')">
            <form method="POST" action="{{ route('expenses.suppliers.store') }}" class="space-y-4">
                @csrf
                @include('expenses._supplier_fields', ['supplier' => null])
                <x-primary-button class="w-full">{{ __('Add supplier') }}</x-primary-button>
            </form>
        </x-card>

        <div class="space-y-4 lg:col-span-2">
            @forelse ($suppliers as $supplier)
                <x-card :padding="false" x-data="{ editing: false }">
                    <div x-show="! editing" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5">
                        <div class="min-w-0 space-y-1.5">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $supplier->name }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if ($supplier->phone){{ $supplier->phone }} · @endif
                                {{ trans_choice('{0} no purchases yet|{1} :count purchase|[2,*] :count purchases', $supplier->purchases_count) }}
                                @if ($supplier->purchases_count) · {{ $money($supplier->purchases_sum_amount) }} · {{ __('last :date', ['date' => \Illuminate\Support\Carbon::parse($supplier->purchases_max_spent_on)->format('d/m/Y')]) }}@endif
                            </p>
                            @if ($supplier->items->isNotEmpty())
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($supplier->items as $item)
                                        <x-badge>{{ $item->name }}</x-badge>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-gray-400">{{ __('No items linked yet. They are added as you buy them.') }}</p>
                            @endif
                            @if ($supplier->notes)<p class="text-xs text-gray-500 dark:text-gray-400">{{ $supplier->notes }}</p>@endif
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <x-link-button variant="secondary" class="h-8 px-2.5 text-xs" :href="route('expenses.index', ['kind' => 'supplier', 'group' => $supplier->id, 'period' => '30d'])">{{ __('Purchases') }}</x-link-button>
                            <button type="button" x-on:click="editing = true" class="btn btn-ghost h-8 px-2.5 text-xs">{{ __('Edit') }}</button>
                            @unless ($supplier->purchases_count)
                                <form method="POST" action="{{ route('expenses.suppliers.destroy', $supplier) }}"
                                      onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Delete :name?', ['name' => $supplier->name])) }})">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-ghost h-8 px-2.5 text-xs !text-red-600 dark:!text-red-400">{{ __('Delete') }}</button>
                                </form>
                            @endunless
                        </div>
                    </div>

                    <form x-show="editing" x-cloak method="POST" action="{{ route('expenses.suppliers.update', $supplier) }}" class="space-y-4 p-4 sm:p-5">
                        @csrf
                        @method('PUT')
                        @include('expenses._supplier_fields', ['supplier' => $supplier])
                        <div class="flex gap-2">
                            <x-primary-button>{{ __('Save') }}</x-primary-button>
                            <x-secondary-button x-on:click="editing = false">{{ __('Cancel') }}</x-secondary-button>
                        </div>
                    </form>
                </x-card>
            @empty
                <div class="surface px-6 py-14 text-center">
                    <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300"><x-icon name="inventory" /></span>
                    <h3 class="mt-3 text-base font-semibold text-gray-900 dark:text-white">{{ __('No suppliers yet') }}</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">{{ __('Add the bakery that sells your buns, the meat supplier… then register each purchase: activating it adds the items to stock.') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
