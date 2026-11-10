<?php

namespace App\Http\Controllers;

use App\Enums\StockProcessingOutcome;
use App\Exceptions\Saipos\SaiposApiException;
use App\Exceptions\Stock\StockProcessingException;
use App\Models\Order;
use App\Services\Stock\OrderRetryService;
use Illuminate\Http\RedirectResponse;

class RetryOrderController extends Controller
{
    public function __invoke(Order $order, OrderRetryService $retryService): RedirectResponse
    {
        $id = ['order' => $order->saipos_order_id];

        if (! $order->canRetry()) {
            return back()->with('error', __("Order :order can't be retried: it is :state.", $id + [
                'state' => mb_strtolower($order->stockState()->label()),
            ]));
        }

        try {
            $result = $retryService->retry($order);
        } catch (StockProcessingException|SaiposApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', match ($result->outcome) {
            StockProcessingOutcome::Processed => __('Stock deducted for order :order.', $id),
            StockProcessingOutcome::AlreadyProcessed => __('Order :order was already deducted.', $id),
            StockProcessingOutcome::SkippedCancelled => __('Order :order is cancelled; nothing deducted.', $id),
        });
    }
}
