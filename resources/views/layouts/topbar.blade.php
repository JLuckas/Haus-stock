{{-- Top bar: menu (phones/tablets), breadcrumb, search / command palette, New menu, theme and account. --}}
@php
    $current = \App\Support\Navigation::current();
    $user = Auth::user();
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
@endphp

<header class="sticky top-0 z-30 border-b border-gray-200/80 bg-white/80 backdrop-blur-md supports-[backdrop-filter]:bg-white/70 dark:border-gray-800 dark:bg-gray-950/80 dark:supports-[backdrop-filter]:bg-gray-950/70">
    <div class="app-container flex h-16 items-center gap-3">
        <button type="button" x-on:click="nav = true" class="btn btn-ghost btn-icon -ml-2 lg:hidden" aria-controls="sidebar" :aria-expanded="nav.toString()">
            <span class="sr-only">{{ __('Open menu') }}</span>
            <x-icon name="menu" />
        </button>

        {{-- Breadcrumb --}}
        @if ($current)
            <nav class="flex min-w-0 items-center gap-1.5 text-sm" aria-label="{{ __('Breadcrumb') }}">
                <span class="hidden text-gray-500 sm:inline dark:text-gray-400">{{ $current[0] }}</span>
                <x-icon name="chevron-right" class="hidden h-3.5 w-3.5 text-gray-300 sm:block dark:text-gray-600" />
                <a href="{{ $current[2] }}" class="truncate font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400">{{ $current[1] }}</a>
            </nav>
        @endif

        <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
            {{-- Search / jump to (Ctrl/⌘ K) --}}
            <button type="button" x-on:click="$dispatch('open-command-palette')"
                    class="flex h-9 items-center gap-2 rounded-lg border border-gray-200 bg-white px-2.5 text-sm text-gray-500 shadow-xs transition hover:border-gray-300 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-200 md:w-64">
                <x-icon name="search" class="h-4 w-4" />
                <span class="hidden md:inline">{{ __('Search or jump to…') }}</span>
                <span class="ml-auto hidden items-center gap-0.5 md:flex"><kbd class="kbd" x-data x-text="navigator.platform.includes('Mac') ? '⌘' : 'Ctrl'">Ctrl</kbd><kbd class="kbd">K</kbd></span>
                <span class="sr-only md:hidden">{{ __('Search') }}</span>
            </button>

            {{-- New … --}}
            <x-dropdown align="right" width="w-60">
                <x-slot name="trigger">
                    <button type="button" class="btn btn-primary h-9 px-2.5 sm:px-3.5" aria-haspopup="menu">
                        <x-icon name="plus" class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ __('New') }}</span>
                        <span class="sr-only sm:hidden">{{ __('New') }}</span>
                    </button>
                </x-slot>
                <x-slot name="content">
                    @foreach (\App\Support\Navigation::quickActions() as $action)
                        <x-dropdown-link :href="$action['href']">
                            <x-icon :name="$action['icon']" class="h-4 w-4 text-gray-400" />
                            {{ $action['label'] }}
                        </x-dropdown-link>
                    @endforeach
                </x-slot>
            </x-dropdown>

            <x-theme-toggle class="hidden sm:block" />

            {{-- Account --}}
            <x-dropdown align="right" width="w-64">
                <x-slot name="trigger">
                    <button type="button" class="flex items-center gap-2 rounded-full p-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" aria-haspopup="menu">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 text-xs font-semibold text-white ring-2 ring-white dark:ring-gray-900">{{ $initials ?: '?' }}</span>
                        <span class="sr-only">{{ __('Account menu for :name', ['name' => $user->name]) }}</span>
                    </button>
                </x-slot>
                <x-slot name="content">
                    <div class="border-b border-gray-100 px-3 py-2.5 dark:border-gray-800">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                    </div>
                    <div class="px-3 py-2.5 sm:hidden">
                        <x-theme-toggle variant="segmented" />
                    </div>
                    <div class="py-1">
                        <x-dropdown-link :href="route('profile.edit')">
                            <x-icon name="user" class="h-4 w-4 text-gray-400" />
                            {{ __('Profile') }}
                        </x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                <x-icon name="logout" class="h-4 w-4 text-gray-400" />
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </div>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</header>
