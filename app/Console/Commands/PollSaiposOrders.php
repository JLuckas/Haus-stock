<?php

namespace App\Console\Commands;

use App\DataTransferObjects\Saipos\NormalizedOrder;
use App\Enums\StockProcessingOutcome;
use App\Exceptions\Saipos\SaiposApiException;
use App\Models\Order;
use App\Services\Saipos\SaiposIntegrationService;
use App\Services\Stock\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Long-running poller. Run it under a process supervisor (Supervisor, systemd, Docker restart policy):
 *
 *   php artisan saipos:poll
 *
 * Each cycle re-reads a lookback window from Saipos; already-processed orders are skipped locally,
 * so overlapping windows and restarts never double-deduct stock.
 */
class PollSaiposOrders extends Command
{
    protected $signature = 'saipos:poll
        {--once : Run a single polling cycle and exit}
        {--interval= : Seconds to sleep between cycles (default: SAIPOS_POLL_INTERVAL or 30)}
        {--lookback= : Minutes of order history read each cycle (default: SAIPOS_POLL_LOOKBACK_MINUTES or 60)}
        {--max-memory=128 : Exit cleanly above this many MB so the supervisor starts a fresh process}';

    protected $description = 'Poll Saipos for new orders and deduct ingredients from stock using each product\'s BoM';

    private bool $shouldRun = true;

    public function handle(SaiposIntegrationService $saipos, StockService $stockService): int
    {
        $interval = max(5, (int) ($this->option('interval') ?? config('saipos.polling.interval_seconds')));
        $lookback = max(1, (int) ($this->option('lookback') ?? config('saipos.polling.lookback_minutes')));
        $maxMemoryMb = max(32, (int) $this->option('max-memory'));

        // Finish the current cycle before exiting on SIGTERM/SIGINT (only where pcntl exists, i.e. not Windows).
        $this->trap(fn () => [SIGTERM, SIGINT], function (): void {
            $this->shouldRun = false;
        });

        $this->components->info("Polling Saipos every {$interval}s, lookback {$lookback} min.");

        while (true) {
            try {
                $this->runCycle($saipos, $stockService, $lookback);
            } catch (SaiposApiException $e) {
                // Raised only for configuration errors: retrying every 30s would just flood the logs.
                $this->components->error($e->getMessage());
                Log::critical('Saipos poller stopped: configuration error.', ['error' => $e->getMessage()]);

                return self::FAILURE;
            }

            if ($this->option('once') || ! $this->shouldRun) {
                break;
            }

            if (memory_get_usage(true) / 1_048_576 >= $maxMemoryMb) {
                $this->components->warn("Memory above {$maxMemoryMb} MB; exiting so the supervisor restarts the poller.");
                break;
            }

            // A trapped signal interrupts sleep(), so shutdown is not delayed by the interval.
            sleep($interval);

            if (! $this->shouldRun) {
                break;
            }
        }

        $this->components->info('Saipos poller stopped.');

        return self::SUCCESS;
    }

    private function runCycle(SaiposIntegrationService $saipos, StockService $stockService, int $lookbackMinutes): void
    {
        try {
            $orders = $saipos->fetchRecentOrders(now()->subMinutes($lookbackMinutes));
        } catch (SaiposApiException $e) {
            if ($e->isConfigurationError) {
                throw $e;
            }

            // Transient (network, 5xx, expired credentials): log and try again next cycle.
            Log::error('Saipos polling cycle failed.', ['error' => $e->getMessage()]);
            $this->components->error($e->getMessage());

            return;
        }

        $processedIds = $this->alreadyProcessedIds($orders);
        $counts = ['processed' => 0, 'skipped' => 0, 'cancelled' => 0, 'failed' => 0];

        foreach ($orders as $order) {
            // Cheap local short-circuit; StockService re-checks under a row lock anyway.
            // Cancelled orders still go through so a late cancellation updates the local status.
            if (! $order->isCancelled() && isset($processedIds[$order->saiposOrderId])) {
                $counts['skipped']++;

                continue;
            }

            try {
                $result = $stockService->processOrder($order);

                $counts[match ($result->outcome) {
                    StockProcessingOutcome::Processed => 'processed',
                    StockProcessingOutcome::AlreadyProcessed => 'skipped',
                    StockProcessingOutcome::SkippedCancelled => 'cancelled',
                }]++;
            } catch (Throwable $e) {
                // One bad order (e.g. unmapped SKU) must not block the rest; it is retried next cycle.
                $counts['failed']++;
                Log::error('Stock deduction failed for Saipos order.', [
                    'saipos_order_id' => $order->saiposOrderId,
                    'error' => $e->getMessage(),
                ]);
                $this->components->error("Order {$order->saiposOrderId}: {$e->getMessage()}");
            }
        }

        $this->line(sprintf(
            '[%s] fetched=%d processed=%d skipped=%d cancelled=%d failed=%d',
            now()->toDateTimeString(),
            count($orders),
            ...array_values($counts),
        ));
    }

    /**
     * @param  list<NormalizedOrder>  $orders
     * @return array<string, int>
     */
    private function alreadyProcessedIds(array $orders): array
    {
        if ($orders === []) {
            return [];
        }

        return Order::query()
            ->whereIn('saipos_order_id', array_map(fn (NormalizedOrder $o): string => $o->saiposOrderId, $orders))
            ->where('is_stock_processed', true)
            ->pluck('saipos_order_id')
            ->flip()
            ->all();
    }
}
