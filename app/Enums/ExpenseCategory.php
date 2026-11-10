<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Bills = 'bills';
    case Rent = 'rent';
    case Transport = 'transport';
    case Supplies = 'supplies';
    case Maintenance = 'maintenance';
    case Taxes = 'taxes';
    case Marketing = 'marketing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bills => __('Bills (power, water, internet)'),
            self::Rent => __('Rent'),
            self::Transport => __('Transport'),
            self::Supplies => __('Supplies and packaging'),
            self::Maintenance => __('Maintenance'),
            self::Taxes => __('Taxes and fees'),
            self::Marketing => __('Marketing'),
            self::Other => __('Other'),
        };
    }
}
