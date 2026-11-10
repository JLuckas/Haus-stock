<?php

use App\Http\Controllers\Api\ProfitabilityReportController;
use Illuminate\Support\Facades\Route;

// Every API route needs a Sanctum token (create one with `php artisan api:token user@example.com`).
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/reports/profitability', ProfitabilityReportController::class)->name('api.reports.profitability');
});
