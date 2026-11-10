<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('layouts.partials.head')
    </head>
    {{-- App shell: fixed sidebar (drawer on phones/tablets), sticky top bar, content column. --}}
    <body class="h-full bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100"
          x-data="{ nav: false }" x-on:keydown.escape.window="nav = false">
        <a href="#main" class="sr-only z-[60] rounded-lg bg-white px-3 py-2 text-sm font-medium shadow-pop focus:not-sr-only focus:fixed focus:left-4 focus:top-4 dark:bg-gray-900">{{ __('Skip to content') }}</a>

        @include('layouts.sidebar')

        <div class="flex min-h-full flex-col transition-[padding] duration-200 lg:pl-64 collapsed:pl-[4.5rem]">
            @include('layouts.topbar')

            <main id="main" class="flex-1 pb-12 pt-6 sm:pt-8" tabindex="-1">
                <div class="app-container space-y-6">
                    @isset($header)
                        {{ $header }}
                    @endisset

                    {{ $slot }}
                </div>
            </main>
        </div>

        <x-flash />
        <x-command-palette />
    </body>
</html>
