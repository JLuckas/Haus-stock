<?php

namespace App\Enums;

enum ExpenseKind: string
{
    /** A day payment to someone working a shift. */
    case Personnel = 'personnel';

    /** Everything else: bills, rent, transport… */
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Personnel => __('Personnel'),
            self::General => __('General expenses'),
        };
    }
}
