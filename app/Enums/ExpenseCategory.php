<?php

namespace App\Enums;

/**
 * Category of a bill or of a general expense. Each category belongs to one of those two kinds
 * (see kind()); personnel and supplier expenses have none.
 */
enum ExpenseCategory: string
{
    // Bills (Contas)
    case Electricity = 'electricity';
    case Water = 'water';
    case Internet = 'internet';
    case Gas = 'gas';
    case Rent = 'rent';
    case Taxes = 'taxes';
    case Software = 'software';
    case Bills = 'bills';           // any other bill (also what older "power, water, internet" entries used)

    // General expenses
    case Transport = 'transport';
    case Supplies = 'supplies';
    case Maintenance = 'maintenance';
    case Marketing = 'marketing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Electricity => __('Electricity'),
            self::Water => __('Water'),
            self::Internet => __('Internet and phone'),
            self::Gas => __('Gas'),
            self::Rent => __('Rent'),
            self::Taxes => __('Taxes and fees'),
            self::Software => __('Software and subscriptions'),
            self::Bills => __('Other bill'),
            self::Transport => __('Transport'),
            self::Supplies => __('Supplies and packaging'),
            self::Maintenance => __('Maintenance'),
            self::Marketing => __('Marketing'),
            self::Other => __('Other'),
        };
    }

    public function kind(): ExpenseKind
    {
        return match ($this) {
            self::Electricity, self::Water, self::Internet, self::Gas, self::Rent, self::Taxes, self::Software, self::Bills => ExpenseKind::Bill,
            default => ExpenseKind::General,
        };
    }

    /** @return list<self> */
    public static function forKind(ExpenseKind $kind): array
    {
        return array_values(array_filter(self::cases(), fn (self $c): bool => $c->kind() === $kind));
    }
}
