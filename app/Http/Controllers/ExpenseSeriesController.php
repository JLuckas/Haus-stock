<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseSeries;
use App\Services\Finance\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Despesas → Recorrentes: every recurring series with its progress and what is still to come. */
class ExpenseSeriesController extends Controller
{
    public function index(): View
    {
        $series = ExpenseSeries::query()
            ->with(['expenses' => fn ($q) => $q->with('tags')])
            ->latest('id')
            ->get()
            ->map(function (ExpenseSeries $s): array {
                $occurrences = $s->expenses;
                $past = $occurrences->filter(fn (Expense $e) => ! $e->isScheduled());
                $future = $occurrences->filter(fn (Expense $e) => $e->isScheduled());

                return [
                    'series' => $s,
                    'template' => $occurrences->first(),
                    'occurrences' => $occurrences,
                    'done' => $past->count(),
                    'remaining' => $future->count(),
                    'next' => $future->first(),
                    'last' => $occurrences->last(),
                    'total' => (float) $occurrences->sum('amount'),
                    'remainingTotal' => (float) $future->sum('amount'),
                    // Open-ended series keep going until stopped; counted ones end with their last occurrence.
                    'finished' => $s->isOpenEnded() ? $s->isStopped() : $future->isEmpty(),
                ];
            })
            ->filter(fn (array $row) => $row['template'] !== null)
            // Series still running first, then finished ones.
            ->sortBy(fn (array $row) => [$row['finished'] ? 1 : 0, $row['next']?->spent_on?->timestamp ?? PHP_INT_MAX])
            ->values();

        return view('expenses.series', [
            'rows' => $series,
            'counts' => ExpenseController::tabCounts(),
        ]);
    }

    /** Stops a series: deletes its future occurrences ("upcoming") or every occurrence ("all"). */
    public function destroy(Request $request, ExpenseSeries $series, ExpenseService $expenses): RedirectResponse
    {
        $what = $request->validate(['what' => ['required', 'in:upcoming,all']])['what'];

        if ($what === 'all') {
            $count = $expenses->delete($series->expenses()->firstOrFail(), ExpenseService::SCOPE_ALL);

            return back()->with('success', __(':count expenses deleted.', ['count' => $count]));
        }

        if ($series->isStopped() && ! $series->expenses()->upcoming()->exists()) {
            return back()->with('error', __('This series has no upcoming expenses.'));
        }

        $count = $expenses->stop($series);

        return back()->with('success', __('Series stopped: :count upcoming expenses removed.', ['count' => $count]));
    }
}
