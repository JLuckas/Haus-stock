<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSupplierPurchaseRequest;
use App\Models\Expense;
use App\Services\Finance\SupplierPurchaseService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Despesas → Fornecedores: purchases whose items go into stock when activated (received). */
class SupplierPurchaseController extends Controller
{
    public function __construct(private readonly SupplierPurchaseService $purchases) {}

    public function store(SaveSupplierPurchaseRequest $request): RedirectResponse
    {
        $receive = $request->boolean('receive_now');
        $purchase = $this->purchases->save(new Expense, $request->validated(), $request->user()->id, $receive, $request->boolean('update_costs'));

        return to_route('expenses.index', ['kind' => 'supplier'] + $request->only(['period', 'from', 'to']))
            ->with('success', $receive
                ? __('Purchase of :amount from :supplier saved and its items added to stock.', ['amount' => Money::format($purchase->amount), 'supplier' => $purchase->supplier->name])
                : __('Purchase of :amount from :supplier saved. Activate it when the goods arrive to add them to stock.', ['amount' => Money::format($purchase->amount), 'supplier' => $purchase->supplier->name]));
    }

    public function edit(Expense $purchase): View
    {
        return view('expenses.purchase-edit', [
            'purchase' => $purchase->load(['items.inventoryItem', 'supplier', 'tags']),
            'tagSuggestions' => ExpenseController::tagSuggestions(),
        ] + ExpenseController::purchaseFormData($purchase));
    }

    public function update(SaveSupplierPurchaseRequest $request, Expense $purchase): RedirectResponse
    {
        $data = $request->validated();
        if ($purchase->isReceived()) {
            unset($data['items']);
        }

        $this->purchases->save($purchase, $data);

        return to_route('expenses.index', 'supplier')->with('success', __('Purchase updated.'));
    }

    /** "Activate": the goods arrived, add them to stock. */
    public function receive(Request $request, Expense $purchase): RedirectResponse
    {
        $done = $this->purchases->receive($purchase, $request->boolean('update_costs', true));

        return back()->with($done ? 'success' : 'error', $done
            ? __('Stock updated with the purchase from :supplier.', ['supplier' => $purchase->supplier->name])
            : __('This purchase was already added to stock.'));
    }

    /** Take the items back out of stock, so the purchase can be corrected. */
    public function unreceive(Expense $purchase): RedirectResponse
    {
        $this->purchases->unreceive($purchase);

        return to_route('expenses.purchases.edit', $purchase)->with('success', __('Stock entry undone. You can now change the items.'));
    }

    public function destroy(Expense $purchase): RedirectResponse
    {
        $wasReceived = $purchase->isReceived();
        $this->purchases->delete($purchase);

        return back(fallback: route('expenses.index', 'supplier'))->with('success', $wasReceived
            ? __('Purchase deleted and its items taken out of stock.')
            : __('Purchase deleted.'));
    }
}
