<x-app-layout>
    @php
        $money = fn ($v) => \App\Support\Money::format($v);
        $personnel = $kind === \App\Enums\ExpenseKind::Personnel;
        // Query parameters every link on this page keeps.
        $keep = array_filter(['tag' => $activeTag?->slug, 'group' => $activeGroup]);
        $link = fn (array $override = []) => route('expenses.index', ['kind' => $kind->value] + $period->query() + array_filter(array_merge($keep, $override), fn ($v) => $v !== null));
    @endphp

    <x-slot name="title">{{ __('Expenses') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Expenses')" :subtitle="__('Staff day payments and running costs. They are subtracted in Finance → Results.')" />
    </x-slot>

    <x-tabs :label="__('Expenses')" :tabs="[
        ['href' => route('expenses.index', ['kind' => 'personnel'] + $period->query()), 'label' => __('Personnel'), 'active' => $personnel, 'count' => $counts['personnel']],
        ['href' => route('expenses.index', ['kind' => 'general'] + $period->query()), 'label' => __('General expenses'), 'active' => ! $personnel, 'count' => $counts['general']],
    ]" />

    <x-period-filter :period="$period" route="expenses.index" :params="['kind' => $kind->value]" :query="$keep" />

    {{-- Totals: the period, then each role / category (click to filter) --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4 2xl:grid-cols-5">
        <div class="col-span-2 surface p-4 sm:p-5 md:col-span-1">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $personnel ? __('Personnel total') : __('Expenses total') }}</p>
            <p class="mt-1 truncate text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $money($total) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ trans_choice('{0} no entries|{1} :count entry|[2,*] :count entries', $count) }} · {{ $period->label() }}</p>
        </div>
        @foreach (array_slice($groups, 0, $personnel ? 3 : 4) as $g)
            <a href="{{ $link(['group' => $activeGroup === $g['key'] ? null : $g['key']]) }}" @class([
                'block rounded-lg p-4 shadow-sm ring-1 transition hover:shadow dark:shadow-none',
                'bg-indigo-50 ring-indigo-300 dark:bg-indigo-500/10 dark:ring-indigo-500/40' => $activeGroup === $g['key'],
                'bg-white ring-transparent dark:bg-gray-900 dark:ring-gray-800' => $activeGroup !== $g['key'],
            ])>
                <p class="truncate text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $g['label'] }}</p>
                <p class="mt-1 truncate text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $money($g['total']) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ trans_choice('{0} no entries|{1} :count entry|[2,*] :count entries', $g['count']) }}</p>
            </a>
        @endforeach
    </div>

    @if ($activeTag || $activeGroup)
        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Filtered by') }}
            @if ($activeTag)<x-tag :tag="$activeTag" />@endif
            @if ($activeGroup)
                <x-badge color="indigo">{{ ($personnel ? \App\Enums\EmployeeRole::from($activeGroup) : \App\Enums\ExpenseCategory::from($activeGroup))->label() }}</x-badge>
            @endif
            <a href="{{ route('expenses.index', ['kind' => $kind->value] + $period->query()) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">{{ __('Clear filters') }}</a>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Quick add --}}
        <x-card id="expense-form" :title="$personnel ? __('Register a day payment') : __('Register an expense')" class="scroll-mt-24 lg:sticky lg:top-24">
            <form method="POST" action="{{ route('expenses.store', $period->query()) }}" class="space-y-5">
                @csrf
                @include('expenses._form')
                <x-primary-button class="w-full">{{ __('Save') }}</x-primary-button>
            </form>
        </x-card>

        <div class="space-y-6 lg:col-span-2">
            {{-- Entries, one card per day with its subtotal --}}
            @forelse ($days as $date => $entries)
                @php($day = \Illuminate\Support\Carbon::parse($date))
                <x-card :padding="false">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-6 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            {{ $day->isToday() ? __('Today') : ($day->isYesterday() ? __('Yesterday') : ucfirst($day->translatedFormat('l'))) }}
                            <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $day->format('d/m/Y') }}</span>
                        </h3>
                        <span class="text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $money($entries->sum('amount')) }}</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($entries as $expense)
                            <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $expense->title() }}</span>
                                        @if ($personnel)
                                            <x-badge color="indigo">{{ $expense->employee_role?->label() }}</x-badge>
                                        @elseif ($expense->description)
                                            <x-badge>{{ $expense->category?->label() }}</x-badge>
                                        @endif
                                    </div>
                                    @if ($expense->tags->isNotEmpty())
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($expense->tags as $tag)
                                                <x-tag :tag="$tag" :href="$link(['tag' => $tag->slug])" />
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($expense->notes)
                                        <p class="break-words text-xs text-gray-500 dark:text-gray-400">{{ $expense->notes }}</p>
                                    @endif
                                </div>
                                <div class="flex shrink-0 items-center justify-between gap-4 sm:justify-end">
                                    <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $money($expense->amount) }}</span>
                                    <span class="flex items-center gap-3 text-sm font-medium">
                                        <a href="{{ route('expenses.edit', $expense) }}" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('expenses.destroy', $expense) }}"
                                              onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Delete this expense of :amount?', ['amount' => $money($expense->amount)])) }})">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">{{ __('Delete') }}</button>
                                        </form>
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @empty
                <x-card>
                    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No expenses in this period.') }}</p>
                </x-card>
            @endforelse

            {{-- Totals per tag — click a tag to filter --}}
            @if ($tagTotals)
                <x-card :title="__('By tag')">
                    <ul class="space-y-3">
                        @foreach ($tagTotals as $row)
                            <li>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <x-tag :tag="$row['tag']" :href="$link(['tag' => $activeTag?->is($row['tag']) ? null : $row['tag']->slug])" />
                                    <span class="tabular-nums text-gray-900 dark:text-gray-100">{{ $money($row['total']) }} <span class="text-xs text-gray-500 dark:text-gray-400">· {{ $row['count'] }}×</span></span>
                                </div>
                                <div class="mt-1.5 h-1.5 rounded-full bg-gray-100 dark:bg-gray-800">
                                    <div class="h-1.5 rounded-full bg-indigo-500" style="width: {{ $total > 0 ? min(100, $row['total'] / $total * 100) : 0 }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ __('An expense with several tags counts in each of them.') }}</p>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
