<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A catalogue category: for ingredients (Pão, Carne, Queijo, Molho…) or for products
 * (Box, Hambúrguer, Bebida…). Items of the same category can replace each other when an
 * order is customized (Cheddar → Mussarela, Coca → Guaraná).
 *
 * @property int $id
 * @property string $scope   ingredient | product
 * @property string $name
 * @property string $slug
 * @property string $color   one of Tag::COLORS
 * @property int $position
 */
class Category extends Model
{
    public const INGREDIENT = 'ingredient';

    public const PRODUCT = 'product';

    protected $fillable = ['scope', 'name', 'slug', 'color', 'position'];

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeForIngredients(Builder $query): Builder
    {
        return $query->where('scope', self::INGREDIENT)->orderBy('position')->orderBy('name');
    }

    public function scopeForProducts(Builder $query): Builder
    {
        return $query->where('scope', self::PRODUCT)->orderBy('position')->orderBy('name');
    }
}
