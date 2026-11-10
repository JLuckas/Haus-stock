<x-app-layout>
    <x-slot name="title">{{ __('Edit expense') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Edit expense')" :subtitle="$expense->kind->label().' · '.$expense->spent_on->format('d/m/Y')" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('expenses.update', $expense) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('expenses._form')

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                <x-link-button variant="secondary" :href="route('expenses.index', $expense->kind->value)">{{ __('Cancel') }}</x-link-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
