<?php

namespace App\Http\Requests;

use App\Enums\InventoryItemType;
use App\Enums\UnitOfMeasure;
use App\Models\InventoryItem;
use Closure;
use App\Http\Requests\Concerns\MapsInventoryCost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * current_stock is deliberately not editable here: overwriting it from a form would erase
 * deductions the poller made after the page was loaded. Stock moves only through
 * "Receive stock" and deductions, which are atomic.
 */
class UpdateInventoryItemRequest extends FormRequest
{
    use MapsInventoryCost;

    public function rules(): array
    {
        /** @var InventoryItem $item */
        $item = $this->route('item');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('inventory_items', 'name')->ignore($item)->withoutTrashed()],
            'type' => [
                'required',
                Rule::enum(InventoryItemType::class),
                function (string $attribute, mixed $value, Closure $fail) use ($item): void {
                    if ($value !== InventoryItemType::StandaloneProduct->value && $item->standaloneProducts()->exists()) {
                        $fail(__('This item is linked to standalone products, so it must stay a standalone product.'));
                    }
                },
            ],
            'unit_of_measure' => ['required', Rule::enum(UnitOfMeasure::class)],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'cost' => $this->costRules(),
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('name'),
            'type' => __('type'),
            'unit_of_measure' => __('unit'),
            'minimum_stock' => __('minimum stock'),
            'cost' => __('cost'),
        ];
    }
}
