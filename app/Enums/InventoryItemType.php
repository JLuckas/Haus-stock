<?php

namespace App\Enums;

enum InventoryItemType: string
{
    /** Raw material consumed fractionally through a recipe (meat, buns, sauces). */
    case Ingredient = 'ingredient';

    /** Item resold as-is and counted in whole units (e.g. 2L Coke Zero). */
    case StandaloneProduct = 'standalone_product';

    public function label(): string
    {
        return match ($this) {
            self::Ingredient => __('Ingredient'),
            self::StandaloneProduct => __('Standalone product'),
        };
    }
}
