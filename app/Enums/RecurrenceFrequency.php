<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * How often a recurring expense repeats. Dates are always counted from the first one, so a
 * monthly series starting on Jan 31 runs Jan 31 → Feb 28 → Mar 31 (no drift to the 28th).
 */
enum RecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => __('Daily'),
            self::Weekly => __('Weekly'),
            self::Biweekly => __('Every 2 weeks'),
            self::Monthly => __('Monthly'),
            self::Quarterly => __('Every 3 months'),
            self::Yearly => __('Yearly'),
        };
    }

    /** Date of occurrence number $index (0 = the first one). */
    public function dateFor(CarbonImmutable $start, int $index): CarbonImmutable
    {
        return match ($this) {
            self::Daily => $start->addDays($index),
            self::Weekly => $start->addWeeks($index),
            self::Biweekly => $start->addWeeks(2 * $index),
            self::Monthly => $start->addMonthsNoOverflow($index),
            self::Quarterly => $start->addMonthsNoOverflow(3 * $index),
            self::Yearly => $start->addYearsNoOverflow($index),
        };
    }

    /** @return list<CarbonImmutable> */
    public function dates(CarbonImmutable $start, int $occurrences): array
    {
        return array_map(fn (int $i): CarbonImmutable => $this->dateFor($start, $i), range(0, max(0, $occurrences - 1)));
    }
}
