<?php

namespace App\Support;

/** Brazilian real formatting: 1234.5 => "R$ 1.234,50", -5 => "-R$ 5,00". */
final class Money
{
    public static function format(float|int|string|null $value): string
    {
        $value = round((float) $value, 2);
        $formatted = 'R$ '.number_format(abs($value), 2, ',', '.');

        return $value < 0 ? "-{$formatted}" : $formatted;
    }
}
