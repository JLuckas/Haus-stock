{{--
    Tag picker: type and press Enter (or comma) to add, pick an existing tag from the list,
    Backspace on an empty field removes the last one. Submits tags[] = names; new names are created.
--}}
@props(['name' => 'tags', 'selected' => [], 'suggestions' => [], 'id' => null])

<div x-data="tagInput(@js(['selected' => array_values($selected), 'suggestions' => array_values($suggestions)]))" class="relative" x-on:click.outside="open = false">
    <div class="flex min-h-[2.625rem] flex-wrap items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2 py-1.5 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950">
        <template x-for="(tag, index) in tags" :key="tag">
            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 py-0.5 pl-2 pr-1 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                <span x-text="'#' + tag"></span>
                <button type="button" class="rounded-full px-1 hover:bg-indigo-100 dark:hover:bg-indigo-500/20" x-on:click="remove(index)" :aria-label="@js(__('Remove tag')) + ' ' + tag">&times;</button>
                <input type="hidden" name="{{ $name }}[]" :value="tag">
            </span>
        </template>
        <input type="text" @if ($id) id="{{ $id }}" @endif x-model="query" autocomplete="off"
               x-on:focus="open = true" x-on:input="open = true"
               x-on:keydown.enter.prevent="add(query)" x-on:keydown="if ($event.key === ',') { $event.preventDefault(); add(query) }"
               x-on:keydown.backspace="if (query === '') removeLast()" x-on:keydown.escape="open = false"
               class="min-w-[8rem] flex-1 border-0 bg-transparent p-1 text-sm focus:ring-0 dark:text-gray-100 dark:placeholder-gray-500"
               :placeholder="tags.length ? '' : @js(__('Type a tag and press Enter'))">
    </div>

    <ul x-show="open && options.length" x-cloak
        class="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
        <template x-for="option in options" :key="option">
            <li>
                <button type="button" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                        x-on:click="add(option)">
                    <span x-show="! suggestions.includes(option)" class="text-gray-500 dark:text-gray-400">{{ __('Create') }} </span><span x-text="'#' + option"></span>
                </button>
            </li>
        </template>
    </ul>
</div>
