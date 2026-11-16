<?php

namespace App\Http\Middleware;

use App\Services\Finance\ExpenseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Keeps open-ended recurring expenses (staff day payments with no set number of times) filled in
 * without a background worker: at most every 10 minutes, a signed-in page load creates the
 * occurrences now due within ExpenseService::HORIZON_DAYS. `php artisan expenses:recurring`
 * does the same from the scheduler.
 */
class MaterializeRecurringExpenses
{
    private const EVERY_SECONDS = 600;

    public function __construct(private readonly ExpenseService $expenses) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->isMethod('GET') && Cache::add('expenses:recurring:materialized', true, self::EVERY_SECONDS)) {
            try {
                $this->expenses->materialize();
            } catch (Throwable $e) {
                // Never break a page over this; the next run (or the scheduled command) catches up.
                Cache::forget('expenses:recurring:materialized');
                report($e);
            }
        }

        return $next($request);
    }
}
