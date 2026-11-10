{{-- Ctrl/⌘ K: jump to any page or start an action (state: commandPalette in resources/js/command-palette.js). --}}
<div x-data="commandPalette(@js(\App\Support\Navigation::commands()))"
     x-on:open-command-palette.window="show()"
     x-on:keydown.escape.window="open && close()">
    <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center p-4 pt-[12vh] sm:pt-[18vh]" role="dialog" aria-modal="true" aria-label="{{ __('Search or jump to…') }}">
        <div x-show="open" x-on:click="close()"
             x-transition:enter="transition-opacity duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-gray-950/40 backdrop-blur-sm" aria-hidden="true"></div>

        <div x-show="open"
             x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 scale-[0.98] -translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition duration-100 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-pop dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center gap-3 border-b border-gray-100 px-4 dark:border-gray-800">
                <x-icon name="search" class="h-5 w-5 text-gray-400" />
                <input x-ref="input" x-model="query" x-on:input="active = 0"
                       x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)" x-on:keydown.enter.prevent="go()"
                       type="text" autocomplete="off" spellcheck="false" role="combobox" aria-controls="command-results" aria-expanded="true"
                       :aria-activedescendant="results.length ? 'command-' + active : null"
                       placeholder="{{ __('Search pages and actions…') }}"
                       class="h-14 w-full border-0 bg-transparent p-0 text-base text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-gray-100">
                <kbd class="kbd">Esc</kbd>
            </div>

            <div id="command-results" class="max-h-[50vh] overflow-y-auto p-2" role="listbox">
                <template x-for="group in grouped" :key="group.label">
                    <div class="mb-1 last:mb-0">
                        <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500" x-text="group.label"></p>
                        <template x-for="item in group.items" :key="item.href + item.label">
                            <a :href="item.href" :id="'command-' + item.index" :data-index="item.index" role="option" :aria-selected="(active === item.index).toString()"
                               x-on:mouseenter="active = item.index" x-on:click.prevent="go(item)"
                               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm"
                               :class="active === item.index ? 'bg-indigo-50 text-indigo-900 dark:bg-indigo-500/15 dark:text-white' : 'text-gray-700 dark:text-gray-300'">
                                <span class="flex h-7 w-7 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                                      :class="active === item.index && '!border-indigo-200 !text-indigo-600 dark:!border-indigo-500/40 dark:!text-indigo-300'">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="item.icon === 'plus' ? 'M12 4.5v15m7.5-7.5h-15' : 'M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3'" /></svg>
                                </span>
                                <span class="flex-1 truncate" x-text="item.label"></span>
                                <x-icon name="chevron-right" class="h-4 w-4 opacity-0 transition" x-bind:class="active === item.index && 'opacity-60'" />
                            </a>
                        </template>
                    </div>
                </template>

                <div x-show="results.length === 0" class="px-4 py-10 text-center">
                    <x-icon name="search" class="mx-auto h-6 w-6 text-gray-300 dark:text-gray-600" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Nothing found for') }} “<span class="font-medium text-gray-700 dark:text-gray-200" x-text="query"></span>”</p>
                </div>
            </div>

            <div class="flex items-center gap-4 border-t border-gray-100 px-4 py-2.5 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <span class="flex items-center gap-1"><kbd class="kbd">↑</kbd><kbd class="kbd">↓</kbd> {{ __('to move') }}</span>
                <span class="flex items-center gap-1"><kbd class="kbd">↵</kbd> {{ __('to open') }}</span>
                <span class="ml-auto hidden sm:inline">{{ __('Tip: press / anywhere') }}</span>
            </div>
        </div>
    </div>
</div>
