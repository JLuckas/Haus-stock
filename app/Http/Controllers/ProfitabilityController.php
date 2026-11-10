<?php

namespace App\Http\Controllers;

use App\Enums\ProfitabilityStatus;
use App\Services\Finance\ProfitabilityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Web view of the same data the JSON API returns. */
class ProfitabilityController extends Controller
{
    public function __invoke(Request $request, ProfitabilityService $profitability): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ProfitabilityStatus::class)],
        ]);

        $all = $profitability->allProductMetrics();
        $status = isset($filters['status']) ? ProfitabilityStatus::from($filters['status']) : null;

        return view('reports.profitability', [
            'summary' => $profitability->summarize($all),
            'metrics' => $status ? $all->filter(fn ($m) => $m->status === $status)->values() : $all,
            'currentStatus' => $status,
            'threshold' => $profitability->healthyMarginPercent(),
        ]);
    }
}
