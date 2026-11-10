<?php

namespace App\Exceptions\Stock;

use App\DataTransferObjects\Saipos\NormalizedOrderItem;

class UnmappedProductException extends StockProcessingException
{
    public static function forItem(NormalizedOrderItem $item, string $saiposOrderId): self
    {
        return new self(__('Order :order: item ":item" (SKU :sku) has no matching product. Register it in Products and retry the order.', [
            'order' => $saiposOrderId,
            'item' => $item->name,
            'sku' => $item->skuId ?? '—',
        ]));
    }
}
