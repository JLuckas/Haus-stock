<x-app-layout>
    <x-slot name="title">{{ __('Add product') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Add product')" :subtitle="__('Build the recipe and watch its cost and recommended price add up on the side.')" />
    </x-slot>

    <div x-data="productForm(@js($formState))" class="grid gap-6 pb-24 lg:grid-cols-3 lg:items-start lg:pb-0">
        <x-card class="lg:col-span-2">
            <form method="POST" action="{{ route('products.store') }}" class="flex flex-col gap-8">
                @csrf
                @include('products._form')

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <x-primary-button>{{ __('Save product') }}</x-primary-button>
                    <x-link-button variant="secondary" :href="route('products.index')">{{ __('Cancel') }}</x-link-button>
                </div>
            </form>
        </x-card>

        <aside class="space-y-6 lg:sticky lg:top-24">
            @include('products._cost-panel', ['metrics' => null])
        </aside>
    </div>
</x-app-layout>
