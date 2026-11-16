<x-app-layout>
    @php
        $money = fn ($v) => \App\Support\Money::format($v);
        $personnel = $kind === \App\Enums\ExpenseKind::Personnel;
        $supplierKind = $kind === \App\Enums\ExpenseKind::Supplier;
        $copy = match ($kind) {
            \App\Enums\ExpenseKind::Personnel => [__('Staff day payments. They are subtracted in Finance → Results.'), __('Personnel total'), __('Register a day payment'), __('One payment per person and day.')],
            \App\Enums\ExpenseKind::Bill => [__('Power, water, internet, rent, taxes… Bills can repeat a set number of times.'), __('Bills total'), __('Register a bill'), __('Once, or repeating daily, weekly, monthly…')],
            \App\Enums\ExpenseKind::Supplier => [__('Purchases from suppliers. Activating a purchase adds its items to stock.'), __('Purchases total'), __('New purchase'), __('Pick the supplier and what was bought.')],
            default => [__('Other running costs: transport, maintenance, marketing…'), __('Expenses total'), __('Register an expense'), __('A single expense.')],
        };
        $groupLabel = fn (string $key) => match ($kind) {
            \App\Enums\ExpenseKind::Personnel => \App\Enums\EmployeeRole::from($key)->label(),
            \App\Enums\ExpenseKind::Supplier => collect($groups)->firstWhere('key', $key)['label'] ?? $key,
            default => \App\Enums\ExpenseCategory::from($key)->label(),
        };
        // Query parameters every link on this page keeps.
        $keep = array_filter(['tag' => $activeTag?->slug, 'group' => $activeGroup]);
        $link = fn (array $override = []) => route('expenses.index', ['kind' => $kind->value] + $period->query() + array_filter(array_merge($keep, $override), fn ($v) => $v !== null));
    @endphp

    <x-slot name="title">{{ __('Expenses') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Expenses')" :subtitle="$copy[0]">
            @if ($supplierKind)
                <x-slot name="actions">
                    <x-link-button variant="secondary" :href="route('expenses.suppliers.index')" icon="inventory">{{ __('Manage suppliers') }}</x-link-button>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    @include('expenses._tabs', ['active' => $kind->value])

    <x-period-filter :period="$period" route="expenses.index" :params="['kind' => $kind->value]" :query="$keep" />

    {{-- Totals: the period, then each role / category (click to filter) --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4 2xl:grid-cols-5">
        <div class="col-span-2 surface p-4 sm:p-5 md:col-span-1">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $copy[1] }}</p>
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
                <x-badge color="indigo">{{ $groupLabel($activeGroup) }}</x-badge>
            @endif
            <a href="{{ route('expenses.index', ['kind' => $kind->value] + $period->query()) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">{{ __('Clear filters') }}</a>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Quick add --}}
        <x-card id="expense-form" :title="$copy[2]" :description="$copy[3]" class="scroll-mt-24">
            @if ($supplierKind)
                <form method="POST" action="{{ route('expenses.purchases.store', $period->query()) }}" class="space-y-5">
                    @csrf
                    @include('expenses._purchase_form', ['purchase' => $expense])
                    <x-primary-button class="w-full">{{ __('Save purchase') }}</x-primary-button>
                </form>
            @else
                <form method="POST" action="{{ route('expenses.store', $period->query()) }}" class="space-y-5">
                    @csrf
                    @include('expenses._form')
                    <x-primary-button class="w-full">{{ __('Save') }}</x-primary-button>
                </form>
            @endif
        </x-card>

        <div class="space-y-6 lg:col-span-2">
            {{-- Scheduled occurrences of recurring expenses --}}
            @if ($upcoming->isNotEmpty())
                <x-card :title="__('Coming up')" :description="__('Next 30 days: :amount', ['amount' => $money($next30)])" :padding="false">
                    <x-slot name="actions">
                        <a href="{{ route('expenses.series.index') }}" class="link text-sm">{{ __('Recurring') }}</a>
                    </x-slot>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($upcoming as $next)
                            <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm sm:px-5">
                                <span class="flex min-w-0 items-center gap-3">
                                    <span class="w-20 shrink-0 tabular-nums text-gray-500 dark:text-gray-400">{{ $next->spent_on->format('d/m/Y') }}</span>
                                    <span class="truncate font-medium text-gray-900 dark:text-gray-100">{{ $next->title() }}</span>
                                    @if ($next->series)<span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $next->series->isOpenEnded() ? '#'.$next->occurrence : $next->occurrence.'/'.$next->series->occurrences }}</span>@endif
                                </span>
                                <span class="shrink-0 tabular-nums text-gray-700 dark:text-gray-300">{{ $money($next->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

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
                                        @elseif ($supplierKind)
                                            @if ($expense->isReceived())
                                                <x-badge color="green" dot>{{ __('In stock') }}</x-badge>
                                            @else
                                                <x-badge color="amber" dot>{{ __('Waiting for the goods') }}</x-badge>
                                            @endif
                                        @elseif ($expense->description)
                                            <x-badge>{{ $expense->category?->label() }}</x-badge>
                                        @endif
                                        @if ($expense->series)
                                            <a href="{{ route('expenses.series.index') }}#series-{{ $expense->series_id }}" title="{{ $expense->series->frequency->label() }}"
                                               class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                                                <x-icon name="retry" class="h-3 w-3" />{{ $expense->series->isOpenEnded() ? '#'.$expense->occurrence : $expense->occurrence.'/'.$expense->series->occurrences }}
                                            </a>
                                        @endif
                                        @if ($expense->isScheduled())
                                            <x-badge color="blue" dot>{{ __('Scheduled') }}</x-badge>
                                        @endif
                                    </div>
                                    @if ($supplierKind && $expense->items->isNotEmpty())
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ $expense->items->map(fn ($line) => $line->inventoryItem->formatQuantity($line->quantity).' '.$line->inventoryItem->name)->join(' · ') }}
                                            @if ($expense->description)<span class="text-gray-400 dark:text-gray-500">— {{ $expense->description }}</span>@endif
                                        </p>
                                    @endif
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
                                        @if ($supplierKind && ! $expense->isReceived())
                                            {{-- "Activate": the goods arrived, add them to stock --}}
                                            <form method="POST" action="{{ route('expenses.purchases.receive', $expense) }}"
                                                  onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Add the items of this purchase to stock now?')) }})">
                                                @csrf
                                                <button class="btn btn-primary h-8 px-2.5 text-xs"><x-icon name="receive" class="h-3.5 w-3.5" />{{ __('Activate') }}</button>
                                            </form>
                                        @endif
                                        @if (! $expense->series && $expense->kind->canRepeat())
                                            {{-- One-off staff payment or bill: make it recurring from the edit page --}}
                                            <a href="{{ route('expenses.edit', $expense) }}#repeat-block" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300" title="{{ __('Make it recurring') }}">
                                                <x-icon name="retry" class="h-3.5 w-3.5" />{{ __('Repeat') }}
                                            </a>
                                        @endif
                                        <a href="{{ $supplierKind ? route('expenses.purchases.edit', $expense) : route('expenses.edit', $expense) }}" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">{{ __('Edit') }}</a>
                                        @if ($supplierKind)
                                            <form method="POST" action="{{ route('expenses.purchases.destroy', $expense) }}"
                                                  onsubmit="return confirm({{ \Illuminate\Support\Js::from($expense->isReceived()
                                                      ? __('Delete this purchase of :amount? Its items are taken out of stock again.', ['amount' => $money($expense->amount)])
                                                      : __('Delete this purchase of :amount?', ['amount' => $money($expense->amount)])) }})">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">{{ __('Delete') }}</button>
                                            </form>
                                        @elseif ($expense->series)
                                            {{-- An occurrence of a series: delete just it, it and the next ones, or all of them --}}
                                            <x-dropdown align="right" width="w-60">
                                                <x-slot name="trigger">
                                                    <button type="button" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" aria-haspopup="menu">{{ __('Delete') }} ▾</button>
                                                </x-slot>
                                                <x-slot name="content">
                                                    @foreach (['this' => __('Only this one'), 'following' => __('This and the next ones'), 'all' => __('The whole series')] as $scope => $label)
                                                        <form method="POST" action="{{ route('expenses.destroy', $expense) }}"
                                                              onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Delete: :what?', ['what' => mb_strtolower($label)])) }})">
                                                            @csrf
                                                            @method('DELETE')
                                                            <input type="hidden" name="scope" value="{{ $scope }}">
                                                            <button role="menuitem" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 dark:text-gray-300 dark:hover:bg-red-500/10 dark:hover:text-red-300">
                                                                <x-icon name="trash" class="h-4 w-4 opacity-60" />{{ $label }}
                                                            </button>
                                                        </form>
                                                    @endforeach
                                                </x-slot>
                                            </x-dropdown>
                                        @else
                                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}"
                                                  onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Delete this expense of :amount?', ['amount' => $money($expense->amount)])) }})">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">{{ __('Delete') }}</button>
                                            </form>
                                        @endif
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
