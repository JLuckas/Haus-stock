<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What an order sold, with the price charged and the production cost at the moment
 * stock was deducted (a snapshot: later cost changes don't rewrite history).
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property string|null $saipos_sku_id
 * @property string $name
 * @property bool $is_add_on
 * @property string $quantity
 * @property string $unit_price
 * @property string|null $unit_cost
 * @property bool $cost_is_complete
 */
class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'saipos_sku_id',
        'name',
        'is_add_on',
        'quantity',
        'unit_price',
        'unit_cost',
        'cost_is_complete',
    ];

    protected function casts(): array
    {
        return [
            'is_add_on' => 'boolean',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'cost_is_complete' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
