<?php

namespace App\Http\Requests;

use App\Enums\InventoryItemType;
use App\Enums\UnitOfMeasure;
use App\Http\Requests\Concerns\MapsInventoryCost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    use MapsInventoryCost;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('inventory_items', 'name')->withoutTrashed()],
            'type' => ['required', Rule::enum(InventoryItemType::class)],
            'unit_of_measure' => ['required', Rule::enum(UnitOfMeasure::class)],
            'current_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
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
            'current_stock' => __('opening stock'),
            'minimum_stock' => __('minimum stock'),
            'cost' => __('cost'),
        ];
    }
}
