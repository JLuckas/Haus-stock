<?php

namespace App\Enums;

enum UnitOfMeasure: string
{
    case Grams = 'g';
    case Milliliters = 'ml';
    case Units = 'un';

    public function label(): string
    {
        return match ($this) {
            self::Grams => __('Grams (g)'),
            self::Milliliters => __('Milliliters (ml)'),
            self::Units => __('Units (un)'),
        };
    }

    /**
     * Costs are typed and shown per kg / per L (how suppliers price them) but stored per g / per ml.
     * cost_per_unit = cost per display unit / costDisplayFactor().
     */
    public function costDisplayFactor(): int
    {
        return match ($this) {
            self::Grams, self::Milliliters => 1000,
            self::Units => 1,
        };
    }

    public function costDisplayUnit(): string
    {
        return match ($this) {
            self::Grams => 'kg',
            self::Milliliters => 'L',
            self::Units => 'un',
        };
    }
}
