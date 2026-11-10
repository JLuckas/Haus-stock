<?php

namespace Tests\Unit;

use App\Enums\UnitOfMeasure;
use App\Support\Quantity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Keep these cases in sync with resources/js/quantity.js. */
class QuantityTest extends TestCase
{
    public static function adaptiveCases(): array
    {
        return [
            'grams stay grams below 1 kg' => [850, 'g', '850 g'],
            'fraction of a gram' => [0.5, 'g', '0,5 g'],
            'zero' => [0, 'g', '0 g'],
            'exactly 1 kg' => [1000, 'g', '1 kg'],
            'kg with grams' => [19430, 'g', '19,43 kg'],
            'kg keeps gram precision' => [19430.5, 'g', '19,431 kg'],
            'rounds up into the next unit' => [999.9996, 'g', '1 kg'],
            'rounds up into tons' => [999999.6, 'g', '1 t'],
            'tons' => [1250000, 'g', '1,25 t'],
            'tons keep kg precision' => [1234567, 'g', '1,235 t'],
            'negative stock scales too' => [-2500, 'g', '-2,5 kg'],
            'ml below 1 L' => [15.5, 'ml', '15,5 ml'],
            'liters' => [4000, 'ml', '4 L'],
            'liters with ml' => [1250, 'ml', '1,25 L'],
            'liters never become kL' => [1500000, 'ml', '1.500 L'],
            'units are never scaled' => [1500, 'un', '1.500 un'],
            'fractional units' => [2.5, 'un', '2,5 un'],
            'no unit' => [1.5, null, '1,5'],
        ];
    }

    #[DataProvider('adaptiveCases')]
    public function test_it_adapts_the_unit_so_at_most_three_digits_precede_the_comma(float $value, ?string $unit, string $expected): void
    {
        $this->assertSame($expected, Quantity::format($value, $unit));
    }

    public function test_it_accepts_the_enum_and_database_strings(): void
    {
        $this->assertSame('19,43 kg', Quantity::format('19430.000', UnitOfMeasure::Grams));
    }

    public function test_exact_keeps_the_stored_unit(): void
    {
        $this->assertSame('19.430,5 g', Quantity::exact(19430.5, UnitOfMeasure::Grams));
        $this->assertSame('4.000 ml', Quantity::exact(4000, 'ml'));
    }

    public function test_it_knows_when_the_unit_was_scaled(): void
    {
        $this->assertTrue(Quantity::isScaled(19430, 'g'));
        $this->assertFalse(Quantity::isScaled(850, 'g'));
        $this->assertFalse(Quantity::isScaled(1500, 'un'));
    }

    public function test_input_values_are_machine_readable(): void
    {
        $this->assertSame('19430.5', Quantity::forInput('19430.500'));
        $this->assertSame('160', Quantity::forInput('160.000'));
        $this->assertSame('0', Quantity::forInput(0));
    }
}
