<?php

namespace App\Http\Requests;

use App\Enums\EmployeeRole;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseKind;
use App\Enums\RecurrenceFrequency;
use App\Services\Finance\ExpenseService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'tags' => collect($this->input('tags', []))->filter(fn ($t) => is_string($t) && trim($t) !== '')->values()->all(),
        ]);
    }

    public function rules(): array
    {
        $personnel = ExpenseKind::Personnel->value;
        $bill = ExpenseKind::Bill->value;
        $kind = ExpenseKind::tryFrom((string) $this->input('kind'));

        return [
            // Supplier purchases have their own form (SaveSupplierPurchaseRequest).
            'kind' => ['required', Rule::in([ExpenseKind::Personnel->value, ExpenseKind::Bill->value, ExpenseKind::General->value])],
            'spent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->addYear()->toDateString()],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'employee_name' => ["exclude_unless:kind,{$personnel}", 'required', 'string', 'max:100'],
            'employee_role' => ["exclude_unless:kind,{$personnel}", 'required', Rule::enum(EmployeeRole::class)],
            // Bills and general expenses each have their own categories.
            'category' => ["exclude_if:kind,{$personnel}", 'required', Rule::in(array_column($kind ? ExpenseCategory::forKind($kind) : [], 'value'))],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:40'],

            // Bills repeat N times; staff payments repeat until stopped; general expenses don't repeat ("none" = once).
            'repeat' => ["exclude_unless:kind,{$bill},{$personnel}", 'nullable', Rule::in(['none', ...array_column(RecurrenceFrequency::cases(), 'value')])],
            'occurrences' => ["exclude_unless:kind,{$bill}", 'exclude_if:repeat,none', 'exclude_without:repeat', 'required', 'integer', 'min:2', 'max:'.ExpenseService::MAX_OCCURRENCES],

            // Editing an occurrence of a series: which occurrences the change reaches.
            'scope' => ['nullable', Rule::in([ExpenseService::SCOPE_THIS, ExpenseService::SCOPE_FOLLOWING, ExpenseService::SCOPE_ALL])],
        ];
    }

    public function attributes(): array
    {
        return [
            'spent_on' => __('date'),
            'amount' => __('amount'),
            'employee_name' => __('employee'),
            'employee_role' => __('role'),
            'category' => __('category'),
            'description' => __('description'),
            'notes' => __('notes'),
            'tags' => __('tags'),
            'repeat' => __('repetition'),
            'occurrences' => __('number of times'),
        ];
    }

    /** The frequency chosen for a new expense, or null for a single one. */
    public function frequency(): ?RecurrenceFrequency
    {
        $repeat = $this->validated('repeat');

        return $repeat && $repeat !== 'none' ? RecurrenceFrequency::from($repeat) : null;
    }
}
