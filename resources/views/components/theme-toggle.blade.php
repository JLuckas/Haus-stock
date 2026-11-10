@props(['variant' => 'icon'])

@php
    $labels = [
        'light' => __('Light'),
        'dark' => __('Dark'),
        'system' => __('System'),
    ];
@endphp

<div x-data="themeSwitcher()" {{ $attributes }}>
    @if ($variant === 'segmented')
        <div class="inline-flex w-full rounded-lg bg-gray-100 p-1 dark:bg-gray-800" role="radiogroup" aria-label="{{ __('Theme') }}">
            @foreach ($labels as $value => $label)
                <button type="button" role="radio" x-on:click="set('{{ $value }}')" :aria-checked="(theme === '{{ $value }}').toString()"
                        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition"
                        :class="theme === '{{ $value }}' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-gray-100' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @else
        {{-- One button that cycles light → dark → system. --}}
        <button type="button" x-on:click="cycle()"
                :title="@js(__('Theme')) + ': ' + @js($labels)[theme]"
                :aria-label="@js(__('Theme')) + ': ' + @js($labels)[theme]"
                class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
            {{-- sun --}}
            <svg x-show="theme === 'light'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
            </svg>
            {{-- moon --}}
            <svg x-show="theme === 'dark'" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
            </svg>
            {{-- system (monitor) --}}
            <svg x-show="theme === 'system'" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
            </svg>
        </button>
    @endif
</div>
