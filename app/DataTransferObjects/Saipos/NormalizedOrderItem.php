<?php

namespace App\DataTransferObjects\Saipos;

final readonly class NormalizedOrderItem
{
    /**
     * @param  string|null  $skuId  Saipos integration_code; matches products.saipos_sku_id.
     * @param  float  $quantity  Total units sold. For choice items this is already multiplied by the parent quantity.
     * @param  bool  $isChoice  True for add-ons/complements (Saipos "choice_items").
     * @param  float|null  $unitPrice  Price charged per unit (unit_price / aditional_price); null = use the product's price.
     */
    public function __construct(
        public ?string $skuId,
        public string $name,
        public float $quantity,
        public bool $isChoice = false,
        public ?float $unitPrice = null,
    ) {}
}
