<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Finance\ProfitabilityService;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/reports/profitability  (Authorization: Bearer <token from `php artisan api:token`>)
 *
 * Cost, profit and margin for every product, worst first, plus a summary.
 */
class ProfitabilityReportController extends Controller
{
    public function __invoke(ProfitabilityService $profitability): JsonResponse
    {
        return response()->json($profitability->generateSystemProfitabilityReport());
    }
}
