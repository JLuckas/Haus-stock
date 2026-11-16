<x-app-layout>
    @php
        $money = fn ($v) => \App\Support\Money::format($v);
    @endphp

    <x-slot name="title">{{ __('Recurring expenses') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Expenses')" :subtitle="__('Recurring expenses: every occurrence is created ahead with its own date and counted in Results when that date arrives.')">
            <x-slot name="actions">
                <x-link-button :href="route('expenses.index', 'bill').'#expense-form'" icon="plus">{{ __('New recurring expense') }}</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('expenses._tabs', ['active' => 'series'])

    @forelse ($rows as $row)
        @php
            /** @var \App\Models\ExpenseSeries $series */
            $series = $row['series'];
            $template = $row['template'];
            $progress = $series->occurrences > 0 ? round($row['done'] / max(1, $row['occurrences']->count()) * 100) : 0;
            $finished = $row['finished'];
            $open = $series->isOpenEnded();
        @endphp
        <x-card id="series-{{ $series->id }}" :padding="false" class="scroll-mt-24" x-data="{ open: false }">
            <div class="flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300"><x-icon name="retry" class="h-4 w-4" /></span>
                        <h3 class="truncate text-base font-semibold text-gray-900 dark:text-white">{{ $template->title() }}</h3>
                        <x-badge :color="$template->kind === \App\Enums\ExpenseKind::Personnel ? 'indigo' : 'gray'">
                            {{ $template->kind === \App\Enums\ExpenseKind::Personnel ? $template->employee_role?->label() : $template->category?->label() }}
                        </x-badge>
                        <x-badge :color="$finished ? 'gray' : 'green'" dot>{{ $finished ? __('Finished') : __('Active') }}</x-badge>
                        @if ($open)<x-badge color="indigo">{{ __('No end date') }}</x-badge>@endif
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $series->frequency->label() }} ·
                        @if ($open)
                            {{ __('until stopped') }} · {{ $money($template->amount) }} {{ __('each') }} · {{ __('since :date', ['date' => $series->starts_on->format('d/m/Y')]) }}
                            @if ($series->isStopped()) · {{ __('stopped on :date', ['date' => $series->stopped_on->format('d/m/Y')]) }}@endif
                        @else
                            {{ trans_choice('{1} :count time|[2,*] :count times', $series->occurrences) }} · {{ $money($template->amount) }} {{ __('each') }}
                            · {{ $series->starts_on->format('d/m/Y') }} → {{ $row['last']->spent_on->format('d/m/Y') }}
                        @endif
                    </p>
                    @if ($template->tags->isNotEmpty())
                        <div class="flex flex-wrap gap-1">
                            @foreach ($template->tags as $tag)
                                <x-tag :tag="$tag" />
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="grid shrink-0 grid-cols-3 gap-4 text-sm lg:w-[28rem]">
                    <div>
                        <p class="eyebrow">{{ __('Done') }}</p>
                        @if ($open)
                            <p class="mt-0.5 font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $row['done'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $money($row['total'] - $row['remainingTotal']) }}</p>
                        @else
                            <p class="mt-0.5 font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $row['done'] }}/{{ $row['occurrences']->count() }}</p>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full bg-indigo-500" style="width: {{ $progress }}%"></div></div>
                        @endif
                    </div>
                    <div>
                        <p class="eyebrow">{{ __('Next') }}</p>
                        <p class="mt-0.5 font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $row['next']?->spent_on->format('d/m/Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="eyebrow">{{ $open ? __('Next 30 days') : __('Still to come') }}</p>
                        <p class="mt-0.5 font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $money($row['remainingTotal']) }}</p>
                        @unless ($open)<p class="text-xs text-gray-500 dark:text-gray-400">{{ __('of :total', ['total' => $money($row['total'])]) }}</p>@endunless
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-2.5 dark:border-gray-800 sm:px-5">
                <button type="button" x-on:click="open = ! open" class="link inline-flex items-center gap-1 text-sm" :aria-expanded="open.toString()">
                    <span x-text="open ? @js(__('Hide dates')) : @js(__('See every date'))">{{ __('See every date') }}</span>
                    <x-icon name="chevron-down" class="h-4 w-4 transition" x-bind:class="open && 'rotate-180'" />
                </button>
                <div class="flex flex-wrap items-center gap-2">
                    <x-link-button variant="secondary" class="h-8 px-2.5 text-xs" :href="route('expenses.edit', $row['next'] ?? $template)">{{ __('Edit') }}</x-link-button>
                    @unless ($finished)
                        <form method="POST" action="{{ route('expenses.series.destroy', $series) }}"
                              onsubmit="return confirm({{ \Illuminate\Support\Js::from($open
                                  ? __('Stop this series? No new payments are created and the :count already scheduled are removed; past ones stay.', ['count' => $row['remaining']])
                                  : __('Stop this series? The :count upcoming expenses are removed; past ones stay.', ['count' => $row['remaining']])) }})">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="what" value="upcoming">
                            <button class="btn btn-secondary h-8 px-2.5 text-xs">{{ $open ? __('Stop') : __('Stop (remove upcoming)') }}</button>
                        </form>
                    @endunless
                    <form method="POST" action="{{ route('expenses.series.destroy', $series) }}"
                          onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Delete the whole series, including the :count expenses already counted?', ['count' => $row['done']])) }})">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="what" value="all">
                        <button class="btn btn-ghost h-8 px-2.5 text-xs !text-red-600 dark:!text-red-400">{{ __('Delete series') }}</button>
                    </form>
                </div>
            </div>

            <div x-show="open" x-cloak class="border-t border-gray-100 bg-gray-50/60 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/20 sm:px-5">
                <ul class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($row['occurrences'] as $occurrence)
                        <li class="flex items-center justify-between gap-3">
                            <a href="{{ route('expenses.edit', $occurrence) }}" @class(['tabular-nums hover:underline', 'text-gray-400 dark:text-gray-500' => ! $occurrence->isScheduled(), 'text-gray-800 dark:text-gray-200' => $occurrence->isScheduled()])>
                                {{ $occurrence->occurrence }}. {{ $occurrence->spent_on->format('d/m/Y') }}
                            </a>
                            <span @class(['tabular-nums', 'text-gray-400 dark:text-gray-500' => ! $occurrence->isScheduled(), 'text-gray-800 dark:text-gray-200' => $occurrence->isScheduled()])>{{ $money($occurrence->amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </x-card>
    @empty
        <div class="surface px-6 py-14 text-center">
            <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300"><x-icon name="retry" /></span>
            <h3 class="mt-3 text-base font-semibold text-gray-900 dark:text-white">{{ __('No recurring expenses yet') }}</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">{{ __('Bills repeat a set number of times (rent every month for a year); staff day payments repeat until you stop them (a delivery rider every Saturday).') }}</p>
            <div class="mt-5 flex justify-center gap-2">
                <x-link-button :href="route('expenses.index', 'bill').'#expense-form'" icon="plus">{{ __('Register a bill') }}</x-link-button>
                <x-link-button variant="secondary" :href="route('expenses.index', 'personnel').'#expense-form'">{{ __('Register a day payment') }}</x-link-button>
            </div>
        </div>
    @endforelse
</x-app-layout>
