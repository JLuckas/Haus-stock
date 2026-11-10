<?php

namespace App\Http\Controllers;

use App\Enums\InventoryItemType;
use App\Enums\ProductStockMode;
use App\Exceptions\Stock\InvalidRecipeException;
use App\Http\Requests\SaveProductRequest;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Services\Finance\ProfitabilityService;
use App\Services\Stock\ProductCatalogService;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductCatalogService $catalog,
        private readonly ProfitabilityService $profitability,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'unmapped' => ['nullable', 'boolean'],
        ]);

        $products = Product::query()
            ->with(['standaloneItem', 'ingredients', 'components'])
            ->withCount(['ingredients', 'components'])
            ->search($filters['search'] ?? null)
            ->when($filters['unmapped'] ?? false, fn ($q) => $q->unmapped())
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('products.index', ['products' => $products, 'filters' => $filters]);
    }

    /** Accepts ?sku=&name= so a failed order can link straight to registering its missing product. */
    public function create(Request $request): View
    {
        return view('products.create', $this->formData(new Product([
            'saipos_sku_id' => $request->query('sku'),
            'name' => $request->query('name'),
        ])));
    }

    public function store(SaveProductRequest $request): RedirectResponse
    {
        $product = $this->catalog->save(new Product, $request->validated());

        return to_route('products.index')->with('success', __('":name" created.', ['name' => $product->name]));
    }

    public function edit(Product $product): View
    {
        $product->load(['ingredients', 'components', 'usedIn']);

        // Everything one unit deducts (all nested levels flattened), with its cost and the margin.
        return view('products.edit', $this->formData($product) + [
            'metrics' => $this->profitability->getProfitabilityMetrics($product),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $this->catalog->save($product, $request->validated());

        return to_route('products.edit', $product)->with('success', __('":name" updated.', ['name' => $product->name]));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $parents = $product->usedIn()->pluck('name');

        if ($parents->isNotEmpty()) {
            return back()->with('error', __('":name" is inside :parents. Remove it from there first.', [
                'name' => $product->name,
                'parents' => $parents->join(', ', ' '.__('and').' '),
            ]));
        }

        if ($product->manualDeductions()->exists()) {
            return back()->with('error', __('":name" has manual deductions in its history, so it can\'t be deleted.', ['name' => $product->name]));
        }

        $product->delete();

        return to_route('products.index')->with('success', __('":name" deleted.', ['name' => $product->name]));
    }

    /** Everything the product form and its live cost panel need, including the rows to pre-fill. */
    private function formData(Product $product): array
    {
        $inventory = InventoryItem::query()->orderBy('name')->get();
        $itemsById = $inventory->keyBy('id');

        // A product can't contain itself or anything that already contains it.
        $excluded = $product->exists ? [$product->id, ...$this->catalog->ancestorIds($product)] : [];
        $componentOptions = Product::query()
            ->with(['ingredients', 'components', 'standaloneItem'])
            ->whereKeyNot($excluded)
            ->orderBy('name')
            ->get();

        $stockMode = $product->exists ? ($product->stockMode() ?? ProductStockMode::Recipe) : ProductStockMode::Recipe;

        $ingredients = old('ingredients', $product->exists
            ? $product->ingredients->map(fn (InventoryItem $i): array => [
                'inventory_item_id' => $i->id,
                'quantity_needed' => Quantity::forInput($i->pivot->quantity_needed),
            ])->values()->all()
            : []);
        $components = old('components', $product->exists
            ? $product->components->map(fn (Product $c): array => [
                'product_id' => $c->id,
                'quantity' => Quantity::forInput($c->pivot->quantity),
            ])->values()->all()
            : []);

        // A brand-new form starts with one empty ingredient row to fill in.
        if (! $ingredients && ! $components) {
            $ingredients = [['inventory_item_id' => '', 'quantity_needed' => '']];
        }

        return [
            'product' => $product,
            'inventory' => $inventory,
            'standaloneItems' => $inventory->where('type', InventoryItemType::StandaloneProduct)->values(),
            'componentOptions' => $componentOptions,
            'defaultMargin' => $this->profitability->healthyMarginPercent(),

            // productForm() in resources/js/product-form.js
            'formState' => [
                'mode' => old('stock_mode', $stockMode->value),
                'ingredients' => array_values($ingredients),
                'components' => array_values($components),
                'standaloneId' => (string) old('inventory_item_id', $product->inventory_item_id),
                'units' => $inventory->mapWithKeys(fn (InventoryItem $i): array => [$i->id => $i->unit_of_measure->value]),
                'errors' => session('errors')?->getBag('default')->getMessages() ?? [],
                // Cost per g / ml / un (null = not set in Inventory yet).
                'itemCosts' => $inventory->mapWithKeys(fn (InventoryItem $i): array => [$i->id => $i->hasCost() ? (float) $i->cost_per_unit : null]),
                'itemNames' => $inventory->pluck('name', 'id'),
                // Cost of one unit of each product that can go inside this one (its whole recipe).
                // Unrounded, so 2 × a component adds up exactly like the server does.
                'productCosts' => $componentOptions->mapWithKeys(function (Product $option) use ($itemsById): array {
                    try {
                        $cost = $this->profitability->calculateProductCost($option, $itemsById);

                        return [$option->id => ['cost' => $cost->total, 'missing' => $cost->missingCostItems, 'unmapped' => false]];
                    } catch (InvalidRecipeException) {
                        return [$option->id => ['cost' => null, 'missing' => [], 'unmapped' => true]];
                    }
                }),
                'productNames' => $componentOptions->pluck('name', 'id'),
                'price' => (string) old('price', $product->price ?? '0'),
                'targetMargin' => (string) old('target_margin_percent', $product->target_margin_percent === null ? '' : (float) $product->target_margin_percent),
                'defaultMargin' => $this->profitability->healthyMarginPercent(),
            ],
        ];
    }
}
