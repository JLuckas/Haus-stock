<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Bill of Materials line: how much of an inventory item one unit of a product consumes.
 *
 * @property int $product_id
 * @property int $inventory_item_id
 * @property string $quantity_needed
 */
class RecipeIngredient extends Pivot
{
    protected $table = 'recipe_ingredients';

    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'inventory_item_id',
        'quantity_needed',
    ];

    protected function casts(): array
    {
        return [
            'quantity_needed' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
