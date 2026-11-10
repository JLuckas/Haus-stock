<?php

namespace App\Enums;

/** Where an order came from. */
enum OrderSource: string
{
    /** Imported by the Saipos poller. */
    case Saipos = 'saipos';

    /** Typed in by staff on the "New order" page. */
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Saipos => __('Saipos'),
            self::Manual => __('Manual'),
        };
    }
}
