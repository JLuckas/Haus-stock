<x-app-layout>
    @php
        $money = fn ($v) => \App\Support\Money::format($v);
        // Full class names so Tailwind keeps them.
        $swatch = [
            'blue' => 'bg-blue-500', 'indigo' => 'bg-indigo-500', 'violet' => 'bg-violet-500', 'pink' => 'bg-pink-500',
            'teal' => 'bg-teal-500', 'amber' => 'bg-amber-500', 'cyan' => 'bg-cyan-500', 'gray' => 'bg-gray-400',
        ];
        $colorNames = [
            'blue' => __('Blue'), 'indigo' => __('Indigo'), 'violet' => __('Violet'), 'pink' => __('Pink'),
            'teal' => __('Teal'), 'amber' => __('Amber'), 'cyan' => __('Cyan'), 'gray' => __('Gray'),
        ];
    @endphp

    <x-slot name="title">{{ __('Expense tags') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Expenses')" :subtitle="__('Tags classify expenses across both tabs, e.g. #fixa, #variável, #fornecedor. Create them here or while registering an expense.')" />
    </x-slot>

    @include('expenses._tabs', ['active' => 'tags'])

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        {{-- New tag --}}
        <x-card :title="__('New tag')">
            <form method="POST" action="{{ route('expenses.tags.store') }}" class="space-y-4" x-data="{ color: @js(old('color', 'indigo')), name: @js(old('name', '')) }">
                @csrf
                <x-field :label="__('Name')" for="new-tag-name" error="name">
                    <div class="flex">
                        <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">#</span>
                        <x-text-input id="new-tag-name" name="name" class="block w-full min-w-0 rounded-l-none" maxlength="40" x-model="name" :placeholder="__('e.g. fixa')" required />
                    </div>
                </x-field>
                <fieldset>
                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Colour') }}</legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach (\App\Models\Tag::COLORS as $color)
                            <label class="cursor-pointer" title="{{ $colorNames[$color] }}">
                                <input type="radio" name="color" value="{{ $color }}" x-model="color" class="peer sr-only">
                                <span class="block h-7 w-7 rounded-full {{ $swatch[$color] }} ring-2 ring-transparent ring-offset-2 transition peer-checked:ring-gray-900 peer-focus-visible:ring-indigo-500 dark:ring-offset-gray-900 dark:peer-checked:ring-white"></span>
                                <span class="sr-only">{{ $colorNames[$color] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <x-primary-button class="w-full">{{ __('Create tag') }}</x-primary-button>
            </form>
        </x-card>

        {{-- Existing tags --}}
        <x-card :title="__('Your tags')" :description="__('Renaming a tag to the name of another one merges them.')" :padding="false" class="lg:col-span-2">
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($tags as $tag)
                    <li x-data="{ editing: false, color: @js($tag->color) }" class="px-4 py-3 sm:px-5">
                        <div x-show="! editing" class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <x-tag :tag="$tag" />
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ trans_choice('{0} not used yet|{1} :count expense|[2,*] :count expenses', $tag->expenses_count) }}
                                    @if ($tag->expenses_count) · {{ $money($tag->expenses_sum_amount) }} · {{ __('last :date', ['date' => \Illuminate\Support\Carbon::parse($tag->expenses_max_spent_on)->format('d/m/Y')]) }}@endif
                                </span>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" x-on:click="editing = true" class="btn btn-ghost h-8 px-2.5 text-xs">{{ __('Edit') }}</button>
                                <form method="POST" action="{{ route('expenses.tags.destroy', $tag) }}"
                                      onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Delete #:name? It is removed from :count expenses; the expenses stay.', ['name' => $tag->name, 'count' => $tag->expenses_count])) }})">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-ghost h-8 px-2.5 text-xs !text-red-600 dark:!text-red-400">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </div>

                        <form x-show="editing" x-cloak method="POST" action="{{ route('expenses.tags.update', $tag) }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            @csrf
                            @method('PUT')
                            <div class="flex sm:w-56">
                                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-2.5 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">#</span>
                                <x-text-input name="name" class="block h-9 w-full min-w-0 rounded-l-none" maxlength="40" :value="$tag->name" :aria-label="__('Name')" required />
                            </div>
                            <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ __('Colour') }}">
                                @foreach (\App\Models\Tag::COLORS as $color)
                                    <label class="cursor-pointer" title="{{ $colorNames[$color] }}">
                                        <input type="radio" name="color" value="{{ $color }}" x-model="color" class="peer sr-only">
                                        <span class="block h-6 w-6 rounded-full {{ $swatch[$color] }} ring-2 ring-transparent ring-offset-2 peer-checked:ring-gray-900 peer-focus-visible:ring-indigo-500 dark:ring-offset-gray-900 dark:peer-checked:ring-white"></span>
                                        <span class="sr-only">{{ $colorNames[$color] }}</span>
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
                    <li class="px-6 py-12 text-center">
                        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500"><x-icon name="products" class="h-5 w-5" /></span>
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('No tags yet. Create your first one, e.g. #fixa for costs that come every month.') }}</p>
                    </li>
                @endforelse
            </ul>
        </x-card>
    </div>
</x-app-layout>
