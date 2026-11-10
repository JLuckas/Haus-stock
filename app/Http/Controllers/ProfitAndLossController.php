<?php

namespace App\Http\Controllers;

use App\Services\Finance\ProfitAndLossService;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Resultado": profit from orders minus losses (employee meals, waste…) and operating expenses over a period. */
class ProfitAndLossController extends Controller
{
    public function __invoke(Request $request, ProfitAndLossService $pnl): View
    {
        $period = Period::fromRequest($request);

        return view('reports.pnl', [
            'report' => $pnl->report($period->from, $period->to),
            'period' => $period,
        ]);
    }
}
