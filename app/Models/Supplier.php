<?php

namespace App\Models;

use App\Enums\ExpenseKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Someone the store buys stock from (the bun bakery, the meat supplier…). `items` are what they
 * usually sell: a new purchase from them starts with those lines.
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $notes
 */
class Supplier extends Model
{
    protected $fillable = ['name', 'phone', 'notes'];

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'inventory_item_supplier')->orderBy('name');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Expense::class)->where('kind', ExpenseKind::Supplier);
    }
}
