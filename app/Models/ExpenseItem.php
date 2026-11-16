<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a supplier purchase: how much of an inventory item was bought and what it cost.
 * `quantity` is in the item's stock unit (g, ml or un); people type it in kg / L / un.
 *
 * @property int $id
 * @property int $expense_id
 * @property int $inventory_item_id
 * @property string $quantity
 * @property string $total
 */
class ExpenseItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['expense_id', 'inventory_item_id', 'quantity', 'total'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'total' => 'decimal:2',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class)->withTrashed();
    }

    /** Quantity in the unit suppliers sell in: kg, L or un. */
    public function purchaseQuantity(): float
    {
        return (float) $this->quantity / $this->inventoryItem->unit_of_measure->costDisplayFactor();
    }

    /** Price per kg / L / un on this purchase. */
    public function unitPrice(): ?float
    {
        $quantity = $this->purchaseQuantity();

        return $quantity > 0 ? round((float) $this->total / $quantity, 4) : null;
    }
}
