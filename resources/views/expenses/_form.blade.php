{{--
    Expense fields (inside a <form>). Personnel: who, role, day rate. General: category, description.
    New bills can repeat a number of times (frequency × times); new staff payments can repeat until
    stopped (frequency only). Editing one occurrence of a series asks which occurrences the change reaches.
--}}
@php
    $personnel = $expense->kind === \App\Enums\ExpenseKind::Personnel;
    $selectedTags = old('tags', $expense->relationLoaded('tags') ? $expense->tags->pluck('name')->all() : []);
    $creating = ! $expense->exists;
    // Bills repeat N times (rent × 12); staff payments repeat until stopped (Ana every Saturday); others don't.
    // Also when editing a one-off expense: it becomes the first occurrence of the new series.
    $canRepeat = ($creating || $expense->series_id === null) && $expense->kind->canRepeat();
    $withCount = $expense->kind->repeatsWithCount();
    $inSeries = $expense->exists && $expense->series_id !== null;
    $field = 'rounded-lg border border-gray-300 px-2 py-2.5 text-center text-sm font-medium text-gray-700 transition hover:bg-gray-50 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 peer-checked:ring-1 peer-checked:ring-indigo-500 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800/60 dark:peer-checked:bg-indigo-500/10 dark:peer-checked:text-indigo-300';
@endphp

<div class="space-y-5"
     x-data="expenseForm(@js([
        'kind' => $expense->kind->value,
        'role' => (string) old('employee_role', $expense->employee_role?->value),
        'amount' => (string) old('amount', $expense->exists ? $expense->amount : ''),
        'employees' => $knownEmployees,
        'spentOn' => (string) old('spent_on', $expense->spent_on?->toDateString()),
        'repeat' => (string) old('repeat', 'none'),
        'occurrences' => (string) old('occurrences', ''),
        'maxOccurrences' => \App\Services\Finance\ExpenseService::MAX_OCCURRENCES,
        'withCount' => $withCount,
        'frequencyLabels' => collect(\App\Enums\RecurrenceFrequency::cases())->mapWithKeys(fn ($f) => [$f->value => mb_strtolower($f->label())]),
     ]))">
    <input type="hidden" name="kind" value="{{ $expense->kind->value }}">

    @if ($inSeries)
        {{-- Which occurrences this edit reaches --}}
        <fieldset x-data="{ scope: @js(old('scope', 'this')) }" class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-3 dark:border-indigo-500/30 dark:bg-indigo-500/10">
            <legend class="sr-only">{{ __('Apply the changes to') }}</legend>
            <p class="flex items-center gap-2 text-sm font-medium text-indigo-900 dark:text-indigo-200">
                <x-icon name="retry" class="h-4 w-4" />
                @if ($expense->series->isOpenEnded())
                    {{ __('Occurrence :n of a :frequency expense with no end date', ['n' => $expense->occurrence, 'frequency' => mb_strtolower($expense->series->frequency->label())]) }}
                @else
                    {{ __('Occurrence :n of :total of a :frequency expense', ['n' => $expense->occurrence, 'total' => $expense->series->occurrences, 'frequency' => mb_strtolower($expense->series->frequency->label())]) }}
                @endif
            </p>
            <p class="mt-2 text-xs font-medium uppercase tracking-wide text-indigo-800/80 dark:text-indigo-300/80">{{ __('Apply the changes to') }}</p>
            <div class="mt-1.5 grid gap-2 sm:grid-cols-3">
                @foreach (['this' => __('Only this one'), 'following' => __('This and the next ones'), 'all' => __('The whole series')] as $value => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="scope" value="{{ $value }}" x-model="scope" class="peer sr-only">
                        <span class="flex h-full items-center justify-center bg-white dark:bg-gray-900 {{ $field }}">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-indigo-900/70 dark:text-indigo-200/70" x-show="scope !== 'this'" x-cloak>{{ __('Each occurrence keeps its own date; only the other fields change.') }}</p>
        </fieldset>
    @endif

    <x-field :label="$canRepeat ? __('Date (first one, if it repeats)') : __('Date')" for="spent_on" error="spent_on">
        <x-text-input id="spent_on" name="spent_on" type="date" class="block w-full" x-model="spentOn" :value="old('spent_on', $expense->spent_on?->toDateString())" required />
    </x-field>

    @if ($personnel)
        <x-field :label="__('Employee')" for="employee_name" error="employee_name">
            <x-text-input id="employee_name" name="employee_name" list="known-employees" class="block w-full" autocomplete="off"
                          :value="old('employee_name', $expense->employee_name)" x-on:change="employeeTyped($event.target.value)" x-on:blur="employeeTyped($event.target.value)" required />
            <datalist id="known-employees">
                @foreach (array_keys($knownEmployees) as $name)
                    <option value="{{ $name }}"></option>
                @endforeach
            </datalist>
        </x-field>

        <fieldset>
            <legend class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Role') }}</legend>
            <div class="mt-1 grid grid-cols-3 gap-2" role="radiogroup">
                @foreach (\App\Enums\EmployeeRole::cases() as $role)
                    <label class="cursor-pointer">
                        <input type="radio" name="employee_role" value="{{ $role->value }}" x-model="role" class="peer sr-only" required>
                        <span class="flex h-full items-center justify-center {{ $field }}">{{ $role->label() }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('employee_role')" class="mt-2" />
        </fieldset>
    @else
        <x-field :label="__('Category')" for="category" error="category">
            <x-select id="category" name="category" class="block w-full" required>
                <option value="">{{ __('Choose a category…') }}</option>
                @foreach (\App\Enums\ExpenseCategory::forKind($expense->kind) as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $expense->category?->value) === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </x-select>
        </x-field>

        <x-field :label="__('Description')" for="description" error="description">
            <x-text-input id="description" name="description" class="block w-full" maxlength="255" :value="old('description', $expense->description)" :placeholder="$expense->kind === \App\Enums\ExpenseKind::Bill ? __('e.g. Power bill October') : __('e.g. Motorbike fuel')" />
        </x-field>
    @endif

    <x-field :label="$personnel ? __('Day rate (R$)') : __('Amount (R$)')" for="amount" error="amount">
        <div class="flex">
            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">R$</span>
            <x-decimal-input id="amount" name="amount" decimals="2" fixed="2" class="block w-full min-w-0 rounded-l-none" x-model="amount" required />
        </div>
    </x-field>

    @if ($canRepeat)
        {{-- Repeat. Bills: frequency × number of times, all created now. Staff: frequency only, until stopped. --}}
        <div id="repeat-block" class="scroll-mt-24 rounded-xl border border-gray-200 p-3 dark:border-gray-800" :class="repeating && '!border-indigo-300 bg-indigo-50/40 dark:!border-indigo-500/40 dark:bg-indigo-500/5'">
            @unless ($creating)
                <p class="mb-2 text-xs text-gray-600 dark:text-gray-400">{{ __('Make it recurring: this one becomes the first, on its date.') }}</p>
            @endunless
            <div @class(['grid gap-3', 'grid-cols-2' => $withCount])>
                <x-field :label="__('Repeat')" for="repeat" error="repeat">
                    <x-select id="repeat" name="repeat" class="block w-full" x-model="repeat">
                        <option value="none">{{ __('Does not repeat') }}</option>
                        @foreach (\App\Enums\RecurrenceFrequency::cases() as $frequency)
                            <option value="{{ $frequency->value }}">{{ $frequency->label() }}</option>
                        @endforeach
                    </x-select>
                </x-field>
                @if ($withCount)
                <x-field :label="__('Times')" for="occurrences" error="occurrences" x-show="repeating" x-cloak>
                    <div class="flex">
                        <x-decimal-input id="occurrences" name="occurrences" decimals="0" step="1" min="2" class="block w-full min-w-0" x-model="occurrences"
                                         bind-disabled="! repeating" placeholder="12" :aria-label="__('Number of times')" />
                    </div>
                </x-field>
                @endif
            </div>

            <div x-show="repeating" x-cloak class="mt-3 text-sm" aria-live="polite">
                @unless ($withCount)
                    <template x-if="dates.length">
                        <div class="space-y-1.5">
                            <p class="text-gray-800 dark:text-gray-200">
                                <span class="font-semibold tabular-nums" x-text="amountText"></span> · <span x-text="frequencyLabel"></span>, {{ __('until you stop it') }}
                            </p>
                            <div class="flex flex-wrap gap-1">
                                <template x-for="(date, i) in previewDates" :key="i + date">
                                    <span class="rounded-md bg-white px-1.5 py-0.5 text-xs tabular-nums text-gray-700 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-700" x-text="date"></span>
                                </template>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('The next payments appear ahead of time and each one counts in Results on its date. Stop it under Expenses → Recurring when it ends.') }}</p>
                        </div>
                    </template>
                    <p x-show="! dates.length" class="text-xs text-gray-500 dark:text-gray-400">{{ __('Choose the first date.') }}</p>
                @else
                <template x-if="dates.length">
                    <div class="space-y-1.5">
                        <p class="text-gray-800 dark:text-gray-200">
                            <span class="font-semibold tabular-nums" x-text="count + ' × ' + amountText"></span>
                            = <span class="font-semibold tabular-nums" x-text="seriesTotal"></span>
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Until') }} <span class="font-medium" x-text="lastDate"></span></p>
                        <div class="flex flex-wrap gap-1">
                            <template x-for="(date, i) in previewDates" :key="i + date">
                                <span class="rounded-md bg-white px-1.5 py-0.5 text-xs tabular-nums text-gray-700 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-700" x-text="date"></span>
                            </template>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Each one is counted in Results on its own date. You can change or stop them later.') }}</p>
                    </div>
                </template>
                <p x-show="! dates.length" class="text-xs text-gray-500 dark:text-gray-400">{{ __('Choose the first date and how many times (2 to :max).', ['max' => \App\Services\Finance\ExpenseService::MAX_OCCURRENCES]) }}</p>
                @endunless
            </div>
        </div>
    @endif

    <x-field :label="__('Tags')" for="tags-input" :hint="__('Classify the expense, e.g. #fixa, #fim-de-semana. Press Enter to add.')" error="tags">
        <x-tag-input id="tags-input" :selected="$selectedTags" :suggestions="$tagSuggestions" :manage-url="route('expenses.tags.index')" />
    </x-field>

    <x-field :label="__('Notes (optional)')" for="notes" error="notes">
        <x-textarea id="notes" name="notes" rows="2" maxlength="1000">{{ old('notes', $expense->notes) }}</x-textarea>
    </x-field>
</div>
