<?php

namespace App\DataTransferObjects\Saipos;

final readonly class NormalizedOrder
{
    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * @param  list<NormalizedOrderItem>  $items
     * @param  array<string, mixed>  $rawPayload  Original Saipos payload, stored for auditing.
     * @param  float  $discount  Order-level discount, subtracted from revenue.
     */
    public function __construct(
        public string $saiposOrderId,
        public ?string $status,
        public array $items,
        public array $rawPayload = [],
        public float $discount = 0.0,
    ) {}

    public function isCancelled(): bool
    {
        return strtoupper((string) $this->status) === self::STATUS_CANCELLED;
    }
}
