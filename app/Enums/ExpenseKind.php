<?php

namespace App\Enums;

enum ExpenseKind: string
{
    /** A day payment to someone working a shift. */
    case Personnel = 'personnel';

    /** Bills that come back: power, water, internet, rent, taxes… Can repeat a set number of times. */
    case Bill = 'bill';

    /** A purchase from a supplier: its items go into stock when it is received. */
    case Supplier = 'supplier';

    /** Everything else: transport, maintenance, marketing… */
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Personnel => __('Personnel'),
            self::Bill => __('Bills'),
            self::Supplier => __('Suppliers'),
            self::General => __('General expenses'),
        };
    }

    /**
     * Bills and staff day payments can repeat. Bills repeat a set number of times; staff payments
     * repeat until stopped (no number of times).
     */
    public function canRepeat(): bool
    {
        return $this === self::Bill || $this === self::Personnel;
    }

    /** Whether repeating asks for a number of times (bills) or runs until stopped (staff payments). */
    public function repeatsWithCount(): bool
    {
        return $this === self::Bill;
    }

    /** Whether it counts as an operating expense in the Resultado. Supplier purchases are stock: they reach the result as CMV when sold. */
    public function isOperatingExpense(): bool
    {
        return $this !== self::Supplier;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
