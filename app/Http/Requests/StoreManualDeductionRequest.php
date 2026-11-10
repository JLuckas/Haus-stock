<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualDeductionRequest extends FormRequest
{
    public const TARGET_ITEM = 'item';

    public const TARGET_PRODUCT = 'product';

    protected function prepareForValidation(): void
    {
        if (! $this->filled('target')) {
            $this->merge(['target' => $this->filled('product_id') ? self::TARGET_PRODUCT : self::TARGET_ITEM]);
        }
    }

    public function rules(): array
    {
        return [
            'target' => ['required', Rule::in([self::TARGET_ITEM, self::TARGET_PRODUCT])],
            'inventory_item_id' => ['exclude_unless:target,'.self::TARGET_ITEM, 'required', 'integer', Rule::exists('inventory_items', 'id')->withoutTrashed()],
            'product_id' => ['exclude_unless:target,'.self::TARGET_PRODUCT, 'required', 'integer', Rule::exists('products', 'id')],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'inventory_item_id' => __('item'),
            'product_id' => __('product'),
            'quantity' => __('quantity'),
            'reason' => __('reason'),
        ];
    }

    public function isProduct(): bool
    {
        return $this->validated('target') === self::TARGET_PRODUCT;
    }
}
