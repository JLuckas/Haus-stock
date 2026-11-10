<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\Finance\ProfitabilityMetrics;
use App\Enums\ProfitabilityStatus;
use App\Models\Setting;
use App\Services\Finance\ProfitabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Products → Costs: what one unit of each product costs (all ingredients, every level) and the price its target margin needs. */
class ProductCostController extends Controller
{
    public const FILTERS = ['below_target', 'incomplete'];

    public function __construct(private readonly ProfitabilityService $profitability) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'show' => ['nullable', 'in:'.implode(',', self::FILTERS)],
        ]);

        $all = $this->profitability->allProductMetrics()->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $belowTarget = fn (ProfitabilityMetrics $m): bool => $m->recommendedPrice !== null && $m->price < $m->recommendedPrice;
        $incomplete = fn (ProfitabilityMetrics $m): bool => $m->error !== null || $m->missingCostItems !== [];

        $term = mb_strtolower(trim($filters['search'] ?? ''));
        $rows = $all
            ->when($term !== '', fn ($c) => $c->filter(fn (ProfitabilityMetrics $m): bool => str_contains(mb_strtolower($m->name), $term)
                || str_contains(mb_strtolower($m->saiposSkuId), $term)))
            ->when(($filters['show'] ?? null) === 'below_target', fn ($c) => $c->filter($belowTarget))
            ->when(($filters['show'] ?? null) === 'incomplete', fn ($c) => $c->filter($incomplete))
            ->values();

        return view('products.costs', [
            'rows' => $rows,
            'filters' => $filters,
            'counts' => [
                'all' => $all->count(),
                'below_target' => $all->filter($belowTarget)->count(),
                'incomplete' => $all->filter($incomplete)->count(),
                'loss' => $all->filter(fn (ProfitabilityMetrics $m): bool => $m->status === ProfitabilityStatus::Loss)->count(),
            ],
            'targetMargin' => $this->profitability->healthyMarginPercent(),
        ]);
    }

    /** The store-wide target margin (products without their own target use it). */
    public function updateTargetMargin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target_margin_percent' => ['required', 'numeric', 'min:0', 'max:99'],
        ], [], ['target_margin_percent' => __('target margin')]);

        Setting::put(Setting::TARGET_MARGIN, round((float) $data['target_margin_percent'], 2));

        return back()->with('success', __('Target margin set to :value %.', [
            'value' => number_format((float) $data['target_margin_percent'], 1, ',', '.'),
        ]));
    }
}
