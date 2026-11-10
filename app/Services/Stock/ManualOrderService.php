<?php

namespace App\Services\Stock;

use App\Enums\OrderSource;
use App\Exceptions\Stock\StockProcessingException;
use App\Models\Order;
use App\Models\Product;
use App\Services\Saipos\SaiposIntegrationService;
use Illuminate\Support\Facades\Log;

/**
 * Orders typed in by staff (sales outside Saipos, or while the sync is down).
 *
 * The order is stored with a Saipos-shaped payload and deducted through exactly the same path as
 * imported orders (normalizeOrder → StockService::processOrder): BoM + combos, one transaction,
 * at-most-once. If staff type the real Saipos order number, the poller later sees that order as
 * already processed and does not deduct it again.
 */
class ManualOrderService
{
    public const REFERENCE_PREFIX = 'M-';

    /** No 0/O or 1/I, so references can be read aloud or typed back without mistakes. */
    private const REFERENCE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(
        private readonly SaiposIntegrationService $saipos,
        private readonly StockService $stockService,
    ) {}

    /**
     * Registers the order and deducts its stock. Never throws for stock problems: if the deduction
     * fails, the order is kept as "failed" with the reason in last_error, like an imported order.
     *
     * @param  list<array{product_id: int|string, quantity: float|int|string}>  $lines
     * @param  string|null  $reference  Saipos order number, if the sale exists there; otherwise one is generated.
     */
    public function create(array $lines, ?string $reference, ?string $notes, ?int $userId): Order
    {
        $products = Product::query()->whereKey(array_column($lines, 'product_id'))->get()->keyBy('id');
        $reference = filled($reference) ? trim($reference) : $this->newReference();

        $payload = [
            'order_id' => $reference,
            'status' => 'CONFIRMED',
            'source' => OrderSource::Manual->value,
            'notes' => $notes,
            'items' => array_map(function (array $line) use ($products): array {
                $product = $products[(int) $line['product_id']];

                return [
                    'integration_code' => $product->saipos_sku_id,
                    'desc_item' => $product->name,
                    'quantity' => round((float) $line['quantity'], 3),
                    'unit_price' => (float) $product->price,
                ];
            }, $lines),
        ];

        $order = Order::query()->create([
            'saipos_order_id' => $reference,
            'source' => OrderSource::Manual,
            'created_by' => $userId,
            'notes' => filled($notes) ? trim($notes) : null,
            'status' => 'CONFIRMED',
            'raw_payload' => $payload,
        ]);

        try {
            $this->stockService->processOrder($this->saipos->normalizeOrder($payload));
        } catch (StockProcessingException $e) {
            // Already recorded on the order by StockService; the UI shows it with a Retry button.
            Log::warning('Manual order saved but stock was not deducted.', ['order' => $reference, 'error' => $e->getMessage()]);
        }

        return $order->refresh();
    }

    /** e.g. "M-261109-7K3Q" (date + 4 random characters), unique among orders. */
    public function newReference(): string
    {
        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }

            $reference = self::REFERENCE_PREFIX.now()->format('ymd').'-'.$suffix;
        } while (Order::query()->where('saipos_order_id', $reference)->exists());

        return $reference;
    }
}
