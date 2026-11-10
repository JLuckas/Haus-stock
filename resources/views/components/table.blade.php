{{--
    Data table in a card. Becomes one card per row below 1024px (see .table-stack in app.css):
    give each <td> a data-label, except the row title; put row buttons in <td class="stack-actions">.

    $headers: list of strings or ['label' => …, 'align' => 'right', 'sr' => true (screen-reader only)].
    $groups: true when the slot brings its own <tbody> per row group (e.g. a row plus its expandable details).
--}}
@props(['headers', 'groups' => false])

<x-card :padding="false" {{ $attributes }}>
    <div class="overflow-x-auto">
        <table class="table-stack min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800/40 dark:text-gray-400">
                <tr>
                    @foreach ($headers as $i => $header)
                        @php($h = is_array($header) ? $header : ['label' => $header])
                        <th scope="col" @class([
                            'px-4 py-3',
                            'sm:px-6' => $i === 0 || $i === count($headers) - 1,
                            'text-right' => ($h['align'] ?? null) === 'right',
                        ])>@if ($h['sr'] ?? false)<span class="sr-only">{{ $h['label'] }}</span>@else{{ $h['label'] }}@endif</th>
                    @endforeach
                </tr>
            </thead>
            @if ($groups)
                {{ $slot }}
            @else
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    {{ $slot }}
                </tbody>
            @endif
        </table>
    </div>
</x-card>
