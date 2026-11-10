{{-- Empty state inside a table: icon + message (+ optional "action" slot). --}}
@props(['colspan', 'icon' => 'inbox'])

<tr>
    <td colspan="{{ $colspan }}" class="px-6 py-12 text-center">
        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500"><x-icon :name="$icon" class="h-5 w-5" /></span>
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ $slot }}</p>
        @isset($action)<div class="mt-4">{{ $action }}</div>@endisset
    </td>
</tr>
