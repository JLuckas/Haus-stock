<?php

namespace App\Enums;

/** Where an order stands in the stock pipeline, derived from the orders table columns. */
enum OrderStockState: string
{
    case Processed = 'processed';
    case Failed = 'failed';
    case Pending = 'pending';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Processed => __('Deducted'),
            self::Failed => __('Failed'),
            self::Pending => __('Pending'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /** Badge colour used by the UI. */
    public function color(): string
    {
        return match ($this) {
            self::Processed => 'green',
            self::Failed => 'red',
            self::Pending => 'gray',
            self::Cancelled => 'yellow',
        };
    }
}
