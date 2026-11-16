{{--
    Tag picker. Click a coloured chip to add or remove an existing tag; or type, then Enter (or a comma)
    to add one: arrows pick from the list, a new name is created on save. Backspace on an empty field
    removes the last tag. Submits tags[] = names.

    suggestions: list of ['name' => …, 'color' => …], most used first.
--}}
@props(['name' => 'tags', 'selected' => [], 'suggestions' => [], 'id' => null, 'manageUrl' => null])

<div x-data="tagInput(@js(['selected' => array_values($selected), 'suggestions' => array_values($suggestions)]))" class="space-y-2">
    <div class="relative" x-on:click.outside="open = false">
        <div class="flex min-h-[2.375rem] flex-wrap items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2 py-1.5 shadow-xs transition focus-within:border-indigo-500 focus-within:ring-4 focus-within:ring-indigo-500/15 dark:border-gray-700 dark:bg-gray-950 dark:focus-within:border-indigo-400">
            <template x-for="(tag, index) in tags" :key="tag">
                <span class="inline-flex items-center gap-1 rounded-full py-0.5 pl-2 pr-1 text-xs font-medium ring-1 ring-inset" :class="colorOf(tag)">
                    <span x-text="'#' + tag"></span>
                    <span x-show="isNew(tag)" class="text-[10px] uppercase opacity-70">{{ __('new') }}</span>
                    <button type="button" class="rounded-full px-1 leading-none hover:bg-black/5 dark:hover:bg-white/10" x-on:click="remove(index)" :aria-label="@js(__('Remove tag')) + ' ' + tag">&times;</button>
                    <input type="hidden" name="{{ $name }}[]" :value="tag">
                </span>
            </template>
            <input type="text" @if ($id) id="{{ $id }}" @endif x-model="query" autocomplete="off" role="combobox" :aria-expanded="(open && options.length > 0).toString()"
                   x-on:focus="open = true" x-on:input="open = true; active = 0"
                   x-on:keydown.enter.prevent="pick()" x-on:keydown="if ($event.key === ',') { $event.preventDefault(); add(query) }"
                   x-on:keydown.arrow-down.prevent="open = true; move(1)" x-on:keydown.arrow-up.prevent="move(-1)"
                   x-on:keydown.backspace="if (query === '') removeLast()" x-on:keydown.escape="open = false"
                   class="min-w-[8rem] flex-1 border-0 bg-transparent p-1 text-sm focus:ring-0 dark:text-gray-100 dark:placeholder-gray-500"
                   :placeholder="tags.length ? '' : @js(__('Type a tag and press Enter'))">
        </div>

        <ul x-show="open && options.length" x-cloak role="listbox"
            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-gray-200 bg-white p-1 text-sm shadow-pop dark:border-gray-800 dark:bg-gray-900">
            <template x-for="(option, index) in options" :key="option">
                <li role="option" :aria-selected="(active === index).toString()">
                    <button type="button" class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-gray-700 dark:text-gray-300"
                            :class="active === index ? 'bg-gray-100 dark:bg-gray-800' : ''" x-on:mouseenter="active = index" x-on:click="add(option)">
                        <span x-show="isNew(option)" class="text-gray-500 dark:text-gray-400">{{ __('Create') }}</span>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="colorOf(option)" x-text="'#' + option"></span>
                    </button>
                </li>
            </template>
        </ul>
    </div>

    {{-- One-click chips: the most used tags --}}
    <div x-show="chips.length" class="flex flex-wrap items-center gap-1.5">
        <template x-for="chip in chips" :key="chip.name">
            <button type="button" x-on:click="toggle(chip.name)" :aria-pressed="has(chip.name).toString()"
                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset transition"
                    :class="has(chip.name) ? colorOf(chip.name) : 'bg-white text-gray-500 ring-gray-200 hover:text-gray-800 hover:ring-gray-300 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-700 dark:hover:text-gray-200'">
                <span x-text="has(chip.name) ? '✓' : '+'" class="text-[10px]"></span>
                <span x-text="'#' + chip.name"></span>
            </button>
        </template>
        @if ($manageUrl)
            <a href="{{ $manageUrl }}" class="link ml-1 text-xs">{{ __('Manage tags') }}</a>
        @endif
    </div>
</div>
