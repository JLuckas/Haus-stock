<x-app-layout>
    @php
        $swatch = [
            'blue' => 'bg-blue-500', 'indigo' => 'bg-indigo-500', 'violet' => 'bg-violet-500', 'pink' => 'bg-pink-500',
            'teal' => 'bg-teal-500', 'amber' => 'bg-amber-500', 'cyan' => 'bg-cyan-500', 'gray' => 'bg-gray-400',
        ];
        $sections = [
            ['scope' => \App\Models\Category::INGREDIENT, 'title' => __('Ingredient categories'), 'hint' => __('On an order, an ingredient can be swapped for another of the same category (Cheddar → Mussarela).'), 'rows' => $ingredientCategories, 'count' => 'inventory_items_count', 'members' => 'inventoryItems', 'placeholder' => __('e.g. Cheese')],
            ['scope' => \App\Models\Category::PRODUCT, 'title' => __('Product categories'), 'hint' => __('In a box, a product can be swapped for another of the same category (Coca → Guaraná, one burger for another).'), 'rows' => $productCategories, 'count' => 'products_count', 'members' => 'products', 'placeholder' => __('e.g. Beverage')],
        ];
    @endphp

    <x-slot name="title">{{ __('Categories') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Products')" :subtitle="__('Categories group ingredients and products, so orders can swap one for another of the same kind.')" />
    </x-slot>

    <x-product-tabs />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 xl:items-start">
        @foreach ($sections as $section)
            <x-card :title="$section['title']" :description="$section['hint']" :padding="false">
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($section['rows'] as $category)
                        <li x-data="{ editing: false, color: @js($category->color) }" class="px-4 py-3 sm:px-5">
                            <div x-show="! editing" class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <x-category :category="$category" class="!text-xs" />
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ trans_choice('{0} empty|{1} :count item|[2,*] :count items', $category->{$section['count']}) }}</span>
                                    </div>
                                    @if ($category->{$section['members']}->isNotEmpty())
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $category->{$section['members']}->pluck('name')->take(8)->join(', ') }}@if ($category->{$section['count']} > 8)…@endif</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" x-on:click="editing = true" class="btn btn-ghost h-8 px-2.5 text-xs">{{ __('Edit') }}</button>
                                    <form method="POST" action="{{ route('products.categories.destroy', $category) }}"
                                          onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Delete :name? Its :count items stay, with no category.', ['name' => $category->name, 'count' => $category->{$section['count']}])) }})">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-ghost h-8 px-2.5 text-xs !text-red-600 dark:!text-red-400">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </div>

                            <form x-show="editing" x-cloak method="POST" action="{{ route('products.categories.update', $category) }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                @csrf
                                @method('PUT')
                                <x-text-input name="name" class="block h-9 w-full sm:w-48" maxlength="60" :value="$category->name" :aria-label="__('Name')" required />
                                <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ __('Colour') }}">
                                    @foreach (\App\Models\Tag::COLORS as $color)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="color" value="{{ $color }}" x-model="color" class="peer sr-only">
                                            <span class="block h-6 w-6 rounded-full {{ $swatch[$color] }} ring-2 ring-transparent ring-offset-2 peer-checked:ring-gray-900 dark:ring-offset-gray-900 dark:peer-checked:ring-white"></span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="flex gap-2 sm:ml-auto">
                                    <x-primary-button class="h-8 px-3 text-xs">{{ __('Save') }}</x-primary-button>
                                    <x-secondary-button class="h-8 px-3 text-xs" x-on:click="editing = false">{{ __('Cancel') }}</x-secondary-button>
                                </div>
                            </form>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No categories yet.') }}</li>
                    @endforelse
                </ul>

                {{-- New category --}}
                <form method="POST" action="{{ route('products.categories.store') }}" class="flex flex-col gap-2 border-t border-gray-100 bg-gray-50/60 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/20 sm:flex-row sm:items-center sm:px-5">
                    @csrf
                    <input type="hidden" name="scope" value="{{ $section['scope'] }}">
                    <input type="hidden" name="color" value="gray">
                    <x-text-input name="name" class="block h-9 w-full sm:flex-1" maxlength="60" :placeholder="$section['placeholder']" :aria-label="__('New category')" required />
                    <x-secondary-button type="submit" class="h-9">+ {{ __('Add category') }}</x-secondary-button>
                </form>
            </x-card>
        @endforeach
    </div>
    <x-input-error :messages="$errors->get('slug')" />
</x-app-layout>
