{{--
    Section tabs (underlined). $tabs: list of ['href' => …, 'label' => …, 'active' => bool, 'count' => ?int].
--}}
@props(['tabs', 'label' => null])

<nav {{ $attributes->class('-mb-px flex gap-6 overflow-x-auto border-b border-gray-200 dark:border-gray-800') }} @if ($label) aria-label="{{ $label }}" @endif>
    @foreach ($tabs as $tab)
        <a href="{{ $tab['href'] }}" @if ($tab['active']) aria-current="page" @endif
           @class([
               'inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium transition',
               'border-indigo-500 text-gray-900 dark:text-gray-100' => $tab['active'],
               'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200' => ! $tab['active'],
           ])>
            {{ $tab['label'] }}
            @if (isset($tab['count']))
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs tabular-nums text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $tab['count'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
