<?php

namespace App\Support;

use App\Enums\UnitOfMeasure;

/**
 * Formats stock quantities for people. Stock is stored in base units (g, ml, un); on screen the
 * unit adapts so there are at most 3 digits before the decimal comma:
 *
 *        850 g  →  850 g
 *     19 430 g  →  19,43 kg
 *  1 250 000 g  →  1,25 t
 *      4 000 ml →  4 L
 *
 * Numbers use Brazilian separators (decimal comma, thousands dot), like prices in the app.
 * Mirrored in resources/js/quantity.js for live previews; keep both in sync.
 */
final class Quantity
{
    /** Base unit => [factor, label] from largest to smallest. Units not listed are never scaled. */
    private const SCALES = [
        'g' => [[1_000_000, 't'], [1_000, 'kg'], [1, 'g']],
        'ml' => [[1_000, 'L'], [1, 'ml']],
    ];

    private const DECIMALS = 3;

    /** Adaptive display, e.g. 19430 g => "19,43 kg". Without a unit, only the number is formatted. */
    public static function format(float|int|string|null $value, UnitOfMeasure|string|null $unit = null): string
    {
        [$scaled, $label] = self::scale((float) $value, self::unitValue($unit));

        return self::join(self::number($scaled), $label);
    }

    /** Exact amount in the base unit, e.g. 19430.5 g => "19.430,5 g" (for tooltips and audits). */
    public static function exact(float|int|string|null $value, UnitOfMeasure|string|null $unit = null): string
    {
        return self::join(self::number((float) $value), self::unitValue($unit));
    }

    /** True when format() shows a different unit than the base one (so exact() adds information). */
    public static function isScaled(float|int|string|null $value, UnitOfMeasure|string|null $unit): bool
    {
        $unit = self::unitValue($unit);

        return self::scale((float) $value, $unit)[1] !== $unit;
    }

    /** Machine value for <input type="number">: dot decimal, no grouping, base unit ("19430.5"). */
    public static function forInput(float|int|string|null $value): string
    {
        return self::trimZeros(number_format((float) $value, self::DECIMALS, '.', ''), '.');
    }

    /** @return array{0: float, 1: ?string} [value in the chosen unit, unit label] */
    private static function scale(float $value, ?string $unit): array
    {
        $value = round($value, self::DECIMALS);

        foreach (self::SCALES[$unit] ?? [] as [$factor, $label]) {
            $scaled = round($value / $factor, self::DECIMALS);

            // First unit (largest first) that keeps at least "1" before the comma; base unit as fallback.
            if (abs($scaled) >= 1 || $factor === 1) {
                return [$scaled, $label];
            }
        }

        return [$value, $unit];
    }

    private static function number(float $value): string
    {
        $number = self::trimZeros(number_format($value, self::DECIMALS, ',', '.'), ',');

        return $number === '-0' ? '0' : $number;
    }

    private static function trimZeros(string $number, string $decimalSeparator): string
    {
        return rtrim(rtrim($number, '0'), $decimalSeparator);
    }

    private static function join(string $number, ?string $unit): string
    {
        return $unit ? "{$number} {$unit}" : $number;
    }

    private static function unitValue(UnitOfMeasure|string|null $unit): ?string
    {
        return $unit instanceof UnitOfMeasure ? $unit->value : $unit;
    }
}
