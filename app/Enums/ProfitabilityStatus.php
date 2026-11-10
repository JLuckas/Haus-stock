<?php

namespace App\Enums;

/** Financial health of a product. Cases are declared from most to least urgent. */
enum ProfitabilityStatus: string
{
    /** Selling price is below production cost. */
    case Loss = 'loss';

    /** The recipe can't be resolved (no recipe, loop, broken standalone link): cost unknown. */
    case Unmapped = 'unmapped';

    /** At least one ingredient has no cost yet, so cost is understated and margin overstated. */
    case IncompleteCost = 'incomplete_cost';

    /** Profitable, but the margin is under the healthy threshold. */
    case BelowThreshold = 'below_threshold';

    /** Price is zero: typically only sold inside combos. Margin is undefined. */
    case NoPrice = 'no_price';

    case Healthy = 'healthy';

    public function label(): string
    {
        return match ($this) {
            self::Loss => __('Loss'),
            self::Unmapped => __('No recipe'),
            self::IncompleteCost => __('Missing costs'),
            self::BelowThreshold => __('Low margin'),
            self::NoPrice => __('No price'),
            self::Healthy => __('Healthy'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Loss, self::Unmapped => 'red',
            self::IncompleteCost, self::BelowThreshold => 'yellow',
            self::NoPrice => 'gray',
            self::Healthy => 'green',
        };
    }

    /** Lower = more urgent; used to sort reports worst-first. */
    public function severity(): int
    {
        return array_search($this, self::cases(), true);
    }

    /** Needs attention: losing money, low margin, or numbers that can't be trusted yet. */
    public function isFlagged(): bool
    {
        return in_array($this, [self::Loss, self::Unmapped, self::IncompleteCost, self::BelowThreshold], true);
    }
}
