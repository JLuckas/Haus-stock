{{--
    Sidebar navigation. Desktop (lg+): fixed, can shrink to icons (state in localStorage "sidebar",
    applied before paint as html.sidebar-collapsed). Phones/tablets: a drawer opened from the top bar.
--}}
@php($groups = \App\Support\Navigation::groups())

{{-- Drawer backdrop --}}
<div x-show="nav" x-cloak x-on:click="nav = false"
     x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-40 bg-gray-950/50 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-gray-200 bg-white transition-[transform,width] duration-200 ease-out dark:border-gray-800 dark:bg-gray-900 lg:w-64 lg:translate-x-0 collapsed:w-[4.5rem]"
       :class="nav && '!translate-x-0 shadow-pop'"
       x-data="{
           collapsed: document.documentElement.classList.contains('sidebar-collapsed'),
           toggle() {
               this.collapsed = ! this.collapsed;
               document.documentElement.classList.toggle('sidebar-collapsed', this.collapsed);
               try { localStorage.setItem('sidebar', this.collapsed ? 'collapsed' : 'expanded') } catch (e) {}
           },
       }"
       aria-label="{{ __('Main navigation') }}">

    {{-- Brand --}}
    <div class="flex h-16 shrink-0 items-center justify-between gap-2 px-5 collapsed:justify-center collapsed:px-0">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-500 shadow-xs ring-1 ring-inset ring-black/5">
                <x-application-logo class="h-5 w-5 fill-current text-white" />
            </span>
            <span class="truncate text-[15px] font-semibold tracking-tight text-gray-900 collapsed:hidden dark:text-white">Haus <span class="font-normal text-gray-500 dark:text-gray-400">Stock</span></span>
        </a>
        <button type="button" x-on:click="nav = false" class="btn btn-ghost btn-icon -mr-2 lg:hidden" aria-label="{{ __('Close menu') }}">
            <x-icon name="x" />
        </button>
    </div>

    {{-- Sections --}}
    <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-6 pt-2">
        @foreach ($groups as $group)
            <div>
                <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400 collapsed:hidden dark:text-gray-500">{{ $group['label'] }}</p>
                <div class="mx-auto mb-2 hidden h-px w-6 bg-gray-200 collapsed:block dark:bg-gray-800" aria-hidden="true"></div>
                <ul class="space-y-0.5">
                    @foreach ($group['items'] as $item)
                        <li>
                            <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif title="{{ $item['label'] }}"
                               @class([
                                   'group relative flex h-9 items-center gap-3 rounded-lg px-3 text-sm font-medium transition collapsed:justify-center collapsed:px-0',
                                   'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white' => $item['active'],
                                   'text-gray-600 hover:bg-gray-100/70 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800/60 dark:hover:text-gray-100' => ! $item['active'],
                               ])>
                                @if ($item['active'])
                                    <span class="absolute -left-3 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-indigo-500" aria-hidden="true"></span>
                                @endif
                                <x-icon :name="$item['icon']" :class="$item['active']
                                    ? 'h-5 w-5 text-indigo-600 dark:text-indigo-400'
                                    : 'h-5 w-5 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300'" />
                                <span class="truncate collapsed:sr-only">{{ $item['label'] }}</span>
                                @if ($item['badge'])
                                    <span @class([
                                        'ml-auto rounded-full px-1.5 py-0.5 text-[11px] font-semibold leading-none tabular-nums collapsed:hidden',
                                        'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' => $item['badgeTone'] === 'red',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-300' => $item['badgeTone'] === 'amber',
                                    ])>{{ $item['badge'] }}</span>
                                    <span @class([
                                        'absolute right-3 top-1.5 hidden h-2 w-2 rounded-full ring-2 ring-white collapsed:block dark:ring-gray-900',
                                        'bg-red-500' => $item['badgeTone'] === 'red',
                                        'bg-amber-500' => $item['badgeTone'] === 'amber',
                                    ]) aria-hidden="true"></span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    {{-- Collapse (desktop only) --}}
    <div class="hidden shrink-0 border-t border-gray-200 p-3 dark:border-gray-800 lg:block">
        <button type="button" x-on:click="toggle()" :aria-pressed="collapsed.toString()"
                class="btn btn-ghost h-9 w-full justify-start gap-3 px-3 text-gray-500 collapsed:justify-center collapsed:px-0"
                title="{{ __('Collapse sidebar') }}">
            <x-icon name="chevrons-left" class="h-5 w-5 transition-transform collapsed:rotate-180" />
            <span class="collapsed:sr-only">{{ __('Collapse sidebar') }}</span>
        </button>
    </div>
</aside>
