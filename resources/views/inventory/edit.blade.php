<x-app-layout>
    <x-slot name="title">{{ $item->name }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Edit :name', ['name' => $item->name])" />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('inventory.update', $item) }}" class="flex flex-col gap-6">
            @csrf
            @method('PUT')
            @include('inventory._form')

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                <x-link-button variant="secondary" :href="route('inventory.index')">{{ __('Cancel') }}</x-link-button>
            </div>
        </form>
    </x-card>

    <x-card :title="__('Delete item')" class="max-w-3xl">
        <form method="POST" action="{{ route('inventory.destroy', $item) }}"
              onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Delete :name? This cannot be undone.', ['name' => $item->name])) }})"
              class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            @csrf
            @method('DELETE')
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Items used in a recipe or sold as a standalone product can\'t be deleted. Past deductions of this item stay in the history.') }}</p>
            <x-danger-button class="shrink-0">{{ __('Delete') }}</x-danger-button>
        </form>
    </x-card>
</x-app-layout>
