<?php

namespace App\Http\Requests;

use App\Enums\EmployeeRole;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseKind;
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
        $general = ExpenseKind::General->value;

        return [
            'kind' => ['required', Rule::enum(ExpenseKind::class)],
            'spent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->addYear()->toDateString()],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'employee_name' => ["exclude_unless:kind,{$personnel}", 'required', 'string', 'max:100'],
            'employee_role' => ["exclude_unless:kind,{$personnel}", 'required', Rule::enum(EmployeeRole::class)],
            'category' => ["exclude_unless:kind,{$general}", 'required', Rule::enum(ExpenseCategory::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:40'],
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
        ];
    }
}
