<x-app-layout>
    <x-slot name="title">{{ __('Add inventory item') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Add inventory item')" />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('inventory.store') }}" class="flex flex-col gap-6">
            @csrf
            @include('inventory._form')

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                <x-primary-button>{{ __('Save item') }}</x-primary-button>
                <x-link-button variant="secondary" :href="route('inventory.index')">{{ __('Cancel') }}</x-link-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
