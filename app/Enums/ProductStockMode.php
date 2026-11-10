<?php

namespace App\Enums;

/** How selling a product consumes stock. */
enum ProductStockMode: string
{
    /** Deducts every recipe_ingredients line (BoM). */
    case Recipe = 'recipe';

    /** Deducts exactly 1 unit of a linked standalone_product inventory item. */
    case Standalone = 'standalone';
}
