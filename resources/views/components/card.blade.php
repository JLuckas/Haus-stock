{{-- Content card. Optional: title, description, an "actions" slot in the header, padding=false for edge-to-edge lists/tables. --}}
@props(['title' => null, 'description' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'surface overflow-hidden']) }}>
    @if ($title || isset($actions))
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3.5 sm:px-5 dark:border-gray-800">
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
                @if ($description)<p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $description }}</p>@endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2 text-sm">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div @class(['p-4 sm:p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
