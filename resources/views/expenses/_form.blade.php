{{-- Expense fields (inside a <form>). Personnel: who, role, day rate. General: category, description. --}}
@php
    $personnel = $expense->kind === \App\Enums\ExpenseKind::Personnel;
    $selectedTags = old('tags', $expense->relationLoaded('tags') ? $expense->tags->pluck('name')->all() : []);
@endphp

<div class="space-y-5"
     x-data="expenseForm(@js([
        'kind' => $expense->kind->value,
        'role' => (string) old('employee_role', $expense->employee_role?->value),
        'amount' => (string) old('amount', $expense->exists ? $expense->amount : ''),
        'employees' => $knownEmployees,
     ]))">
    <input type="hidden" name="kind" value="{{ $expense->kind->value }}">

    <x-field :label="__('Date')" for="spent_on" error="spent_on">
        <x-text-input id="spent_on" name="spent_on" type="date" class="block w-full" :value="old('spent_on', $expense->spent_on?->toDateString())" required />
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
                        <span class="flex h-full items-center justify-center rounded-md border border-gray-300 px-2 py-2.5 text-center text-sm font-medium text-gray-700 transition hover:bg-gray-50 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 peer-checked:ring-1 peer-checked:ring-indigo-500 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800/60 dark:peer-checked:bg-indigo-500/10 dark:peer-checked:text-indigo-300">
                            {{ $role->label() }}
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('employee_role')" class="mt-2" />
        </fieldset>
    @else
        <x-field :label="__('Category')" for="category" error="category">
            <x-select id="category" name="category" class="block w-full" required>
                <option value="">{{ __('Choose a category…') }}</option>
                @foreach (\App\Enums\ExpenseCategory::cases() as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $expense->category?->value) === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </x-select>
        </x-field>

        <x-field :label="__('Description')" for="description" error="description">
            <x-text-input id="description" name="description" class="block w-full" maxlength="255" :value="old('description', $expense->description)" :placeholder="__('e.g. Power bill October')" />
        </x-field>
    @endif

    <x-field :label="$personnel ? __('Day rate (R$)') : __('Amount (R$)')" for="amount" error="amount">
        <div class="flex">
            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">R$</span>
            <x-decimal-input id="amount" name="amount" decimals="2" fixed="2" class="block w-full min-w-0 rounded-l-none" x-model="amount" required />
        </div>
    </x-field>

    <x-field :label="__('Tags')" for="tags-input" :hint="__('Classify the expense, e.g. #fixa, #fim-de-semana. Press Enter to add.')" error="tags">
        <x-tag-input id="tags-input" :selected="$selectedTags" :suggestions="$tagSuggestions" />
    </x-field>

    <x-field :label="__('Notes (optional)')" for="notes" error="notes">
        <x-textarea id="notes" name="notes" rows="2" maxlength="1000">{{ old('notes', $expense->notes) }}</x-textarea>
    </x-field>
</div>
