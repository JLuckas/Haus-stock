<?php

namespace App\Models;

use App\Enums\InventoryItemType;
use App\Enums\UnitOfMeasure;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Deleting an item archives it (soft delete): it disappears from stock lists, forms and
 * recipes, while past manual deductions keep pointing at it.
 *
 * @property int $id
 * @property InventoryItemType $type
 * @property string $name
 * @property UnitOfMeasure $unit_of_measure
 * @property string $current_stock
 * @property string $minimum_stock
 * @property string|null $cost_per_unit  Cost per stored unit (per g, ml or un); null = not set yet.
 */
class InventoryItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'unit_of_measure',
        'current_stock',
        'minimum_stock',
        'cost_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'type' => InventoryItemType::class,
            'unit_of_measure' => UnitOfMeasure::class,
            'current_stock' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'cost_per_unit' => 'decimal:6',
        ];
    }

    public function hasCost(): bool
    {
        return $this->cost_per_unit !== null;
    }

    /** Cost per kg / per L / per un, as suppliers price it (stored per g / ml / un). */
    public function costPerDisplayUnit(): ?float
    {
        return $this->hasCost()
            ? round((float) $this->cost_per_unit * $this->unit_of_measure->costDisplayFactor(), 4)
            : null;
    }

    /** "R$ 45,90/kg", or null when no cost is set. */
    public function formattedCost(): ?string
    {
        return $this->hasCost()
            ? Money::format($this->costPerDisplayUnit()).'/'.$this->unit_of_measure->costDisplayUnit()
            : null;
    }

    /** Products whose recipe (BoM) consumes this item. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'recipe_ingredients')
            ->using(RecipeIngredient::class)
            ->withPivot('quantity_needed')
            ->withTimestamps();
    }

    /** Products sold as-is that map 1:1 to this item. */
    public function standaloneProducts(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function manualDeductions(): HasMany
    {
        return $this->hasMany(ManualDeduction::class);
    }

    /** Every manual removal of this item, including those that came from deducting a product. */
    public function deductionLines(): HasMany
    {
        return $this->hasMany(ManualDeductionLine::class);
    }

    public function isStandalone(): bool
    {
        return $this->type === InventoryItemType::StandaloneProduct;
    }

    public function isBelowMinimum(): bool
    {
        return (float) $this->current_stock < (float) $this->minimum_stock;
    }

    public function scopeBelowMinimum(Builder $query): Builder
    {
        return $query->whereColumn('current_stock', '<', 'minimum_stock');
    }

    /** Case-insensitive name search that behaves the same on PostgreSQL and SQLite. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === ''
            ? $query
            : $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%']);
    }

    /**
     * Why this item can't be deleted right now: products would lose part of their stock mapping.
     * Deduction history is not a reason; it keeps pointing at the archived item.
     *
     * @return list<string> Empty when the item can be deleted.
     */
    public function deletionBlockers(): array
    {
        $blockers = [];

        $recipes = $this->products()->orderBy('name')->pluck('name');
        if ($recipes->isNotEmpty()) {
            $blockers[] = __('it is an ingredient of :products', ['products' => $recipes->join(', ', ' '.__('and').' ')]);
        }

        $standalone = $this->standaloneProducts()->orderBy('name')->pluck('name');
        if ($standalone->isNotEmpty()) {
            $blockers[] = __('it is sold as :products', ['products' => $standalone->join(', ', ' '.__('and').' ')]);
        }

        return $blockers;
    }

    /** Name for history screens, flagged when the item has since been deleted. */
    public function displayName(): string
    {
        return $this->trashed() ? __(':name (deleted)', ['name' => $this->name]) : $this->name;
    }

    /** Human display with an adaptive unit, e.g. 19430 g => "19,43 kg". */
    public function formatQuantity(float|int|string|null $value): string
    {
        return Quantity::format($value, $this->unit_of_measure);
    }

    /** Exact amount in the stored unit, e.g. "19.430 g". */
    public function exactQuantity(float|int|string|null $value): string
    {
        return Quantity::exact($value, $this->unit_of_measure);
    }

    /** Whether formatQuantity() switched unit (then exactQuantity() is worth showing too). */
    public function isScaledQuantity(float|int|string|null $value): bool
    {
        return Quantity::isScaled($value, $this->unit_of_measure);
    }
}
