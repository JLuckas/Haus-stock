<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one manual deduction actually took from one inventory item.
 *
 * @property int $id
 * @property int $manual_deduction_id
 * @property int $inventory_item_id
 * @property string $quantity
 */
class ManualDeductionLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'manual_deduction_id',
        'inventory_item_id',
        'quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:6',
        ];
    }

    public function manualDeduction(): BelongsTo
    {
        return $this->belongsTo(ManualDeduction::class);
    }

    public function inventoryItem(): BelongsTo
    {
        // Deleted (archived) items still show up in the history.
        return $this->belongsTo(InventoryItem::class)->withTrashed();
    }
}
