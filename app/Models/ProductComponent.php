<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A product contained in another product, e.g. 1 × Cheeseburger inside "Box Cheeseburger".
 *
 * @property int $parent_product_id
 * @property int $component_product_id
 * @property string $quantity
 */
class ProductComponent extends Pivot
{
    protected $table = 'product_components';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }
}
