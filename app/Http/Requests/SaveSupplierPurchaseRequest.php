<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A purchase from a supplier: who, when, and the items bought (quantity in kg / L / un, line total in R$). */
class SaveSupplierPurchaseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'tags' => collect($this->input('tags', []))->filter(fn ($t) => is_string($t) && trim($t) !== '')->values()->all(),
            // Drop lines left completely empty.
            'items' => collect($this->input('items', []))
                ->filter(fn ($line) => is_array($line) && (filled($line['inventory_item_id'] ?? null) || filled($line['quantity'] ?? null) || filled($line['total'] ?? null)))
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        /** @var Expense|null $purchase */
        $purchase = $this->route('purchase');
        // A received purchase keeps its items (undo the stock entry to change them).
        $locked = $purchase?->isReceived() ?? false;

        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'spent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->addYear()->toDateString()],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:40'],

            'items' => $locked ? ['prohibited'] : ['required', 'array', 'min:1', 'max:50'],
            'items.*.inventory_item_id' => ['required', 'integer', 'distinct', Rule::exists('inventory_items', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.total' => ['required', 'numeric', 'min:0', 'max:9999999'],

            'receive_now' => ['boolean'],
            'update_costs' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => __('Add at least one item to the purchase.'),
            'items.prohibited' => __('This purchase is already in stock. Undo the stock entry to change its items.'),
            'items.*.inventory_item_id.distinct' => __('Each item can appear only once; add up the quantities instead.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => __('supplier'),
            'spent_on' => __('date'),
            'items' => __('items'),
            'items.*.inventory_item_id' => __('item'),
            'items.*.quantity' => __('quantity'),
            'items.*.total' => __('amount'),
        ];
    }
}
