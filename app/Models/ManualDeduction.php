<?php

namespace App\Models;

use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock removed outside of sales. Targets exactly one of:
 *  - an inventory item (quantity_deducted is in the item's unit), or
 *  - a product (quantity_deducted is in product units; its recipe is resolved recursively).
 * `lines` holds what actually left stock in both cases.
 *
 * @property int $id
 * @property int|null $inventory_item_id
 * @property int|null $product_id
 * @property string $quantity_deducted
 * @property string $reason
 * @property int|null $user_id
 */
class ManualDeduction extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'product_id',
        'quantity_deducted',
        'reason',
        'user_id',
        'cost',
        'cost_is_complete',
    ];

    protected function casts(): array
    {
        return [
            'quantity_deducted' => 'decimal:3',
            // Cost of what left stock, snapshotted when deducted (a loss in the P&L).
            'cost' => 'decimal:4',
            'cost_is_complete' => 'boolean',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        // Deleted (archived) items still show up in the history.
        return $this->belongsTo(InventoryItem::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ManualDeductionLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isProductDeduction(): bool
    {
        return $this->product_id !== null;
    }

    /** "2 un" for an item, "1 ×" for a product. */
    public function formattedQuantity(): string
    {
        return $this->isProductDeduction()
            ? Quantity::format($this->quantity_deducted).' ×'
            : $this->inventoryItem->formatQuantity($this->quantity_deducted);
    }

    public function targetName(): string
    {
        return $this->isProductDeduction() ? $this->product->name : $this->inventoryItem->displayName();
    }
}
