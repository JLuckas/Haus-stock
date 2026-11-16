<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The suppliers the store buys stock from, and the items each one sells. */
class SupplierController extends Controller
{
    public function index(): View
    {
        return view('expenses.suppliers', [
            'suppliers' => Supplier::query()
                ->with('items')
                ->withCount('purchases')
                ->withSum('purchases', 'amount')
                ->withMax('purchases', 'spent_on')
                ->orderBy('name')
                ->get(),
            'inventory' => InventoryItem::query()->orderBy('name')->get(['id', 'name', 'unit_of_measure']),
            'counts' => ExpenseController::tabCounts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $supplier = Supplier::query()->create($data);
        $supplier->items()->sync($data['items'] ?? []);

        return back()->with('success', __('Supplier :name added.', ['name' => $supplier->name]));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $this->validated($request, $supplier);
        $supplier->update($data);
        $supplier->items()->sync($data['items'] ?? []);

        return back()->with('success', __('Supplier :name saved.', ['name' => $supplier->name]));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchases()->exists()) {
            return back()->with('error', __(':name has purchases in the history, so it can\'t be deleted.', ['name' => $supplier->name]));
        }

        $supplier->delete();

        return back()->with('success', __('Supplier :name deleted.', ['name' => $supplier->name]));
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('suppliers', 'name')->ignore($supplier)],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['array'],
            'items.*' => ['integer', Rule::exists('inventory_items', 'id')],
        ], [], ['name' => __('name'), 'phone' => __('phone'), 'items' => __('items')]);

        $data['name'] = trim($data['name']);

        return $data;
    }
}
