<?php

namespace App\Console\Commands;

use App\Models\ManualDeduction;
use App\Models\Order;
use App\Services\Finance\FinancialLedger;
use App\Services\Saipos\SaiposIntegrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Values orders and manual deductions recorded before money snapshots existed,
 * using today's ingredient costs (an estimate: the real past costs weren't saved).
 */
class BackfillFinancials extends Command
{
    protected $signature = 'finance:backfill {--all : Re-value every record, not only those without values}';

    protected $description = 'Save revenue and cost for past orders and manual deductions, at current ingredient costs';

    public function handle(FinancialLedger $ledger, SaiposIntegrationService $saipos): int
    {
        $orders = Order::query()
            ->where('is_stock_processed', true)
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('revenue'))
            ->get();

        foreach ($orders as $order) {
            DB::transaction(fn () => $ledger->recordOrder($order, $saipos->normalizeOrder($order->raw_payload ?? [], $order->saipos_order_id)));
        }

        $deductions = ManualDeduction::query()
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('cost'))
            ->get();

        foreach ($deductions as $deduction) {
            DB::transaction(fn () => $ledger->recordManualDeduction($deduction));
        }

        $this->components->info("Valued {$orders->count()} order(s) and {$deductions->count()} manual deduction(s) at current costs.");

        return self::SUCCESS;
    }
}
