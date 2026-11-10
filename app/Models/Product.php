<?php

namespace App\Models;

use App\Enums\ProductStockMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sellable item as registered in Saipos.
 *
 * Stock is consumed in one of two ways:
 *  - Recipe (BoM): `ingredients` (inventory items) and/or `components` (other products, resolved
 *    recursively). E.g. "Box Cheeseburger" = 1 × Cheeseburger + 1 × Fries, where Cheeseburger =
 *    160 g meat + 1 bun + 30 g cheddar.
 *  - Standalone: `standaloneItem` — e.g. "Coke Zero 2L" deducts 1 unit of the matching inventory item.
 *
 * @property int $id
 * @property string $saipos_sku_id
 * @property string $name
 * @property string $price
 * @property int|null $inventory_item_id
 */
class Product extends Model
{
    protected $fillable = [
        'saipos_sku_id',
        'name',
        'price',
        'target_margin_percent',
        'inventory_item_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'target_margin_percent' => 'decimal:2',
        ];
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'recipe_ingredients')
            ->using(RecipeIngredient::class)
            ->withPivot('quantity_needed')
            ->withTimestamps();
    }

    /** Products this product contains (pivot `quantity` = units per 1 unit of this product). */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_components', 'parent_product_id', 'component_product_id')
            ->using(ProductComponent::class)
            ->withPivot('quantity')
            ->withTimestamps()
            ->orderBy('name');
    }

    /** Products (combos) that contain this product. */
    public function usedIn(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_components', 'component_product_id', 'parent_product_id')
            ->using(ProductComponent::class)
            ->withPivot('quantity')
            ->orderBy('name');
    }

    public function standaloneItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function manualDeductions(): HasMany
    {
        return $this->hasMany(ManualDeduction::class);
    }

    /** Null when the product is not mapped to stock yet (orders containing it will fail). */
    public function stockMode(): ?ProductStockMode
    {
        if ($this->inventory_item_id !== null) {
            return ProductStockMode::Standalone;
        }

        return $this->ingredientCount() + $this->componentCount() > 0 ? ProductStockMode::Recipe : null;
    }

    /** Uses withCount('ingredients') or the loaded relation when available, to avoid N+1 queries in lists. */
    public function ingredientCount(): int
    {
        return $this->ingredients_count
            ?? ($this->relationLoaded('ingredients') ? $this->ingredients->count() : $this->ingredients()->count());
    }

    public function componentCount(): int
    {
        return $this->components_count
            ?? ($this->relationLoaded('components') ? $this->components->count() : $this->components()->count());
    }

    /** Products with no recipe, no components and no standalone item. */
    public function scopeUnmapped(Builder $query): Builder
    {
        return $query->whereNull('inventory_item_id')->doesntHave('ingredients')->doesntHave('components');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = mb_strtolower(trim((string) $term));

        return $term === ''
            ? $query
            : $query->where(fn (Builder $q) => $q
                ->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(saipos_sku_id) LIKE ?', ["%{$term}%"]));
    }
}
