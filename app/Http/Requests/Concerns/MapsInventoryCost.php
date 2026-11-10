<?php

namespace App\Http\Requests\Concerns;

use App\Enums\UnitOfMeasure;

/**
 * The form takes the cost per kg / per L / per un (field "cost"); the database stores it
 * per g / per ml / per un (column cost_per_unit). An empty field means "not set".
 */
trait MapsInventoryCost
{
    /** @return array<string, mixed> Validated input ready for InventoryItem::fill(). */
    public function inventoryAttributes(): array
    {
        $data = $this->validated();
        $cost = $data['cost'] ?? null;
        unset($data['cost']);

        $data['cost_per_unit'] = $cost === null
            ? null
            : round((float) $cost / UnitOfMeasure::from($data['unit_of_measure'])->costDisplayFactor(), 6);

        return $data;
    }

    /** @return array<int, string> */
    protected function costRules(): array
    {
        return ['nullable', 'numeric', 'min:0', 'max:99999999'];
    }
}
