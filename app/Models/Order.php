<?php

namespace App\Models;

use App\DataTransferObjects\Saipos\NormalizedOrder;
use App\Enums\OrderSource;
use App\Enums\OrderStockState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Local ledger of orders, imported from Saipos or typed in manually. `is_stock_processed` +
 * the unique `saipos_order_id` guarantee each order deducts stock at most once — also when a
 * manual order is entered with its Saipos number and the poller later imports the same order.
 *
 * @property int $id
 * @property string $saipos_order_id  Saipos order number, or a generated "M-…" reference for manual orders.
 * @property OrderSource $source
 * @property int|null $created_by
 * @property string|null $notes
 * @property string|null $status
 * @property bool $is_stock_processed
 * @property \Illuminate\Support\Carbon|null $stock_processed_at
 * @property string|null $last_error
 * @property array|null $raw_payload
 */
class Order extends Model
{
    protected $fillable = [
        'saipos_order_id',
        'source',
        'created_by',
        'notes',
        'status',
        'is_stock_processed',
        'stock_processed_at',
        'last_error',
        'raw_payload',
        'discount',
        'revenue',
        'cost',
        'cost_is_complete',
    ];

    protected $attributes = [
        'source' => 'saipos',
    ];

    protected function casts(): array
    {
        return [
            'source' => OrderSource::class,
            'is_stock_processed' => 'boolean',
            'stock_processed_at' => 'datetime',
            'raw_payload' => 'array',
            'discount' => 'decimal:2',
            'revenue' => 'decimal:2',
            'cost' => 'decimal:4',
            'cost_is_complete' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Price and cost snapshot of what was sold (filled when stock is deducted). */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isManual(): bool
    {
        return $this->source === OrderSource::Manual;
    }

    public function isCancelled(): bool
    {
        return strtoupper((string) $this->status) === NormalizedOrder::STATUS_CANCELLED;
    }

    public function stockState(): OrderStockState
    {
        return match (true) {
            $this->is_stock_processed => OrderStockState::Processed,
            $this->isCancelled() => OrderStockState::Cancelled,
            $this->last_error !== null => OrderStockState::Failed,
            default => OrderStockState::Pending,
        };
    }

    public function canRetry(): bool
    {
        return in_array($this->stockState(), [OrderStockState::Failed, OrderStockState::Pending], true);
    }

    public function scopeInStockState(Builder $query, OrderStockState $state): Builder
    {
        return match ($state) {
            OrderStockState::Processed => $query->where('is_stock_processed', true),
            OrderStockState::Cancelled => $query->where('is_stock_processed', false)
                ->where('status', NormalizedOrder::STATUS_CANCELLED),
            OrderStockState::Failed => $this->excludeCancelled($query->where('is_stock_processed', false))
                ->whereNotNull('last_error'),
            OrderStockState::Pending => $this->excludeCancelled($query->where('is_stock_processed', false))
                ->whereNull('last_error'),
        };
    }

    private function excludeCancelled(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('status')
            ->orWhere('status', '!=', NormalizedOrder::STATUS_CANCELLED));
    }
}
