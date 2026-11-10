<?php

namespace App\Services\Finance;

use App\DataTransferObjects\Saipos\NormalizedOrder;
use App\Enums\ExpenseKind;
use App\Models\Expense;
use App\Models\ManualDeduction;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Realised profit and losses over a period, from the snapshots taken when stock moved:
 *
 *   revenue        Σ order revenue (price charged − discount), orders whose stock was deducted
 *   CMV            Σ order production cost
 *   gross profit   revenue − CMV
 *   losses         Σ manual deduction cost (employee meals, waste…)
 *                  + cost of orders cancelled after their stock was deducted
 *   expenses       Σ operating expenses (staff day payments, rent, bills…) by their date
 *   net result     gross profit − losses − expenses
 *
 * Orders count on the day their stock was deducted; deductions on the day they were registered.
 */
class ProfitAndLossService
{
    /** Pseudo-reason under which cancelled-after-deduction orders appear in the losses. */
    public const CANCELLED_ORDERS = 'Orders cancelled after deduction';

    public function report(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::instance($from)->startOfDay();
        $to = CarbonImmutable::instance($to)->endOfDay();

        $orders = Order::query()
            ->where('is_stock_processed', true)
            ->whereBetween('stock_processed_at', [$from, $to])
            ->get(['id', 'status', 'stock_processed_at', 'revenue', 'cost', 'discount', 'cost_is_complete']);

        [$cancelled, $sold] = $orders->partition(fn (Order $order): bool => $order->isCancelled());

        $deductions = ManualDeduction::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'reason', 'cost', 'cost_is_complete', 'created_at']);

        $expenses = Expense::query()->between($from, $to)->get(['id', 'kind', 'spent_on', 'amount', 'employee_role', 'category']);

        $revenue = $this->sum($sold, 'revenue');
        $cogs = $this->sum($sold, 'cost');
        $grossProfit = $revenue - $cogs;
        $losses = $this->sum($deductions, 'cost') + $this->sum($cancelled, 'cost');

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => round($revenue, 2),
            'discounts' => round($this->sum($sold, 'discount'), 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin_percent' => $revenue > 0 ? round($grossProfit / $revenue * 100, 2) : null,
            'losses' => round($losses, 2),
            'expenses' => round($expenseTotal = $this->sum($expenses, 'amount'), 2),
            'expenses_by_kind' => $this->expensesByKind($expenses),
            'net' => round($grossProfit - $losses - $expenseTotal, 2),
            'orders_count' => $sold->count(),
            'deductions_count' => $deductions->count(),
            // Values understated because an ingredient had no cost when the stock moved.
            'incomplete' => [
                'orders' => $orders->filter(fn (Order $o): bool => $o->cost_is_complete === false)->count(),
                'deductions' => $deductions->filter(fn (ManualDeduction $d): bool => $d->cost_is_complete === false)->count(),
                // Recorded before money snapshots existed and not backfilled yet (php artisan finance:backfill).
                'unvalued' => $orders->whereNull('revenue')->count() + $deductions->whereNull('cost')->count(),
            ],
            'losses_by_reason' => $this->lossesByReason($deductions, $cancelled),
            'products' => $this->products($from, $to),
            'days' => $days = $this->days($from, $to, $sold, $cancelled, $deductions, $expenses),
            'chart' => $this->chart($days),
        ];
    }

    /**
     * Chart columns: one per day, or one per week (starting Monday) for periods over 62 days,
     * so columns never get thinner than a sliver.
     *
     * @param  list<array{date: string, gross_profit: float, losses: float, net: float}>  $days
     * @return array{granularity: 'day'|'week', buckets: list<array{label: string, title: string, gross_profit: float, losses: float, net: float}>}
     */
    public function chart(array $days): array
    {
        $weekly = count($days) > 62;

        $buckets = collect($days)
            ->groupBy(fn (array $day): string => $weekly
                ? CarbonImmutable::parse($day['date'])->startOfWeek(CarbonInterface::MONDAY)->toDateString()
                : $day['date'])
            ->map(function (Collection $group, string $start) use ($weekly): array {
                $date = CarbonImmutable::parse($start);

                return [
                    'label' => $date->format('d/m'),
                    'title' => $weekly ? __('Week of :date', ['date' => $date->format('d/m/Y')]) : $date->translatedFormat('D, d/m/Y'),
                    'gross_profit' => round($group->sum('gross_profit'), 2),
                    'losses' => round($group->sum('losses'), 2),
                    'expenses' => round($group->sum('expenses'), 2),
                    'net' => round($group->sum('net'), 2),
                ];
            })
            ->values()
            ->all();

        return ['granularity' => $weekly ? 'week' : 'day', 'buckets' => $buckets];
    }

    /** @return list<array{reason: string, count: int, cost: float, share_percent: float}> */
    private function lossesByReason(Collection $deductions, Collection $cancelled): array
    {
        $rows = $deductions
            ->groupBy(fn (ManualDeduction $d): string => trim($d->reason))
            ->map(fn (Collection $group, string $reason): array => [
                'reason' => $reason,
                'count' => $group->count(),
                'cost' => $this->sum($group, 'cost'),
            ])
            ->values();

        if ($cancelled->isNotEmpty()) {
            $rows->push(['reason' => self::CANCELLED_ORDERS, 'count' => $cancelled->count(), 'cost' => $this->sum($cancelled, 'cost')]);
        }

        $total = $rows->sum('cost');

        return $rows
            ->map(fn (array $row): array => $row + ['share_percent' => $total > 0 ? round($row['cost'] / $total * 100, 1) : 0.0])
            ->map(fn (array $row): array => ['cost' => round($row['cost'], 2)] + $row)
            ->sortByDesc('cost')
            ->values()
            ->all();
    }

    /** Profit per product sold in the period (add-ons included), best first. */
    private function products(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = OrderItem::query()
            ->select('order_items.*')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.is_stock_processed', true)
            ->whereBetween('orders.stock_processed_at', [$from, $to])
            ->where(fn ($q) => $q->whereNull('orders.status')->orWhere('orders.status', '!=', NormalizedOrder::STATUS_CANCELLED))
            ->get();

        return $items
            ->groupBy(fn (OrderItem $item): string => $item->product_id ? 'p'.$item->product_id : 'n'.$item->name)
            ->map(function (Collection $group): array {
                $revenue = $group->sum(fn (OrderItem $i): float => (float) $i->unit_price * (float) $i->quantity);
                $cost = $group->sum(fn (OrderItem $i): float => (float) $i->unit_cost * (float) $i->quantity);

                return [
                    'product_id' => $group->first()->product_id,
                    'name' => $group->first()->name,
                    'is_add_on' => (bool) $group->first()->is_add_on,
                    'quantity' => round($group->sum(fn (OrderItem $i): float => (float) $i->quantity), 3),
                    'revenue' => round($revenue, 2),
                    'cost' => round($cost, 2),
                    'profit' => round($revenue - $cost, 2),
                    'margin_percent' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 2) : null,
                    'cost_is_complete' => $group->every(fn (OrderItem $i): bool => $i->cost_is_complete),
                ];
            })
            ->sortByDesc('profit')
            ->values()
            ->all();
    }

    /** One row per calendar day, including empty days, for the chart. */
    private function days(CarbonImmutable $from, CarbonImmutable $to, Collection $sold, Collection $cancelled, Collection $deductions, Collection $expenses): array
    {
        $days = [];

        foreach (CarbonPeriod::create($from->startOfDay(), '1 day', $to->startOfDay()) as $day) {
            $days[$day->toDateString()] = ['date' => $day->toDateString(), 'gross_profit' => 0.0, 'losses' => 0.0, 'expenses' => 0.0];
        }

        foreach ($sold as $order) {
            $days[$order->stock_processed_at->toDateString()]['gross_profit'] += (float) $order->revenue - (float) $order->cost;
        }

        foreach ($cancelled as $order) {
            $days[$order->stock_processed_at->toDateString()]['losses'] += (float) $order->cost;
        }

        foreach ($deductions as $deduction) {
            $days[$deduction->created_at->toDateString()]['losses'] += (float) $deduction->cost;
        }

        foreach ($expenses as $expense) {
            $days[$expense->spent_on->toDateString()]['expenses'] += (float) $expense->amount;
        }

        return array_values(array_map(fn (array $d): array => [
            'date' => $d['date'],
            'gross_profit' => round($d['gross_profit'], 2),
            'losses' => round($d['losses'], 2),
            'expenses' => round($d['expenses'], 2),
            'net' => round($d['gross_profit'] - $d['losses'] - $d['expenses'], 2),
        ], $days));
    }

    private function sum(Collection $models, string $attribute): float
    {
        return (float) $models->sum(fn ($model): float => (float) $model->{$attribute});
    }

    /** @return array{personnel: float, general: float, by_label: list<array{label: string, total: float}>} */
    private function expensesByKind(Collection $expenses): array
    {
        $rows = $expenses
            ->groupBy(fn (Expense $e): string => $e->kind === ExpenseKind::Personnel
                ? __('Personnel').' · '.$e->employee_role?->label()
                : ($e->category?->label() ?? __('Other')))
            ->map(fn (Collection $group, string $label): array => ['label' => $label, 'total' => round($this->sum($group, 'amount'), 2)])
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'personnel' => round($this->sum($expenses->where('kind', ExpenseKind::Personnel), 'amount'), 2),
            'general' => round($this->sum($expenses->where('kind', ExpenseKind::General), 'amount'), 2),
            'by_label' => $rows,
        ];
    }
}
