<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('layouts.partials.head')
    </head>
    {{-- Sign-in pages: form on the left, product panel on the right (large screens). --}}
    <body class="h-full bg-white font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
        <div class="flex min-h-full">
            <div class="relative flex flex-1 flex-col justify-center px-6 py-12 sm:px-10 lg:flex-none lg:basis-[34rem] xl:px-16">
                <div class="absolute end-4 top-4">
                    <x-theme-toggle />
                </div>

                <div class="mx-auto w-full max-w-sm">
                    <a href="/" class="inline-flex items-center gap-2.5">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 shadow-xs ring-1 ring-inset ring-black/5">
                            <x-application-logo class="h-6 w-6 fill-current text-white" />
                        </span>
                        <span class="text-lg font-semibold tracking-tight">Haus <span class="font-normal text-gray-500 dark:text-gray-400">Stock</span></span>
                    </a>

                    <div class="mt-10">
                        {{ $slot }}
                    </div>
                </div>
            </div>

            <div class="relative hidden flex-1 overflow-hidden bg-gray-950 lg:block" aria-hidden="true">
                <div class="absolute inset-0 bg-[radial-gradient(60rem_40rem_at_80%_-10%,rgba(99,102,241,0.45),transparent),radial-gradient(40rem_30rem_at_0%_110%,rgba(245,158,11,0.25),transparent)]"></div>
                <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.04)_1px,transparent_1px)] bg-[size:48px_48px]"></div>

                <div class="relative flex h-full flex-col justify-end p-12 xl:p-16">
                    <p class="max-w-md text-3xl font-semibold leading-tight tracking-tight text-white">{{ __('Stock, costs and results of your burger shop in one place.') }}</p>
                    <ul class="mt-8 grid max-w-lg gap-3 text-sm text-gray-300">
                        <li class="flex items-center gap-3"><x-icon name="check-circle" class="h-5 w-5 text-indigo-400" />{{ __('Saipos orders deduct each ingredient automatically') }}</li>
                        <li class="flex items-center gap-3"><x-icon name="check-circle" class="h-5 w-5 text-indigo-400" />{{ __('Recipe cost and recommended price as you build it') }}</li>
                        <li class="flex items-center gap-3"><x-icon name="check-circle" class="h-5 w-5 text-indigo-400" />{{ __('Profit, losses and expenses by day') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </body>
</html>
