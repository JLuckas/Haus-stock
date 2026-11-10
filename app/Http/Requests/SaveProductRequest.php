<?php

namespace App\Http\Requests;

use App\Enums\InventoryItemType;
use App\Enums\ProductStockMode;
use App\Models\Product;
use App\Services\Stock\ProductCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Drop recipe rows the user added but left completely empty.
        $this->merge([
            'ingredients' => $this->withoutBlankRows('ingredients', ['inventory_item_id', 'quantity_needed']),
            'components' => $this->withoutBlankRows('components', ['product_id', 'quantity']),
        ]);
    }

    public function rules(): array
    {
        $recipe = ProductStockMode::Recipe->value;
        $standalone = ProductStockMode::Standalone->value;

        return [
            'saipos_sku_id' => [
                'required', 'string', 'max:100',
                Rule::unique('products', 'saipos_sku_id')->ignore($this->route('product')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            // Empty = use the store's target margin.
            'target_margin_percent' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'stock_mode' => ['required', Rule::enum(ProductStockMode::class)],

            'inventory_item_id' => [
                "exclude_unless:stock_mode,{$standalone}", 'required', 'integer',
                Rule::exists('inventory_items', 'id')->where('type', InventoryItemType::StandaloneProduct->value)->withoutTrashed(),
            ],

            // A recipe needs at least one ingredient or one product inside it.
            'ingredients' => ["exclude_unless:stock_mode,{$recipe}", 'array', 'required_without:components'],
            'ingredients.*.inventory_item_id' => [
                "exclude_unless:stock_mode,{$recipe}", 'required', 'integer', 'distinct',
                Rule::exists('inventory_items', 'id')->withoutTrashed(),
            ],
            'ingredients.*.quantity_needed' => ["exclude_unless:stock_mode,{$recipe}", 'required', 'numeric', 'gt:0', 'max:999999'],

            'components' => ["exclude_unless:stock_mode,{$recipe}", 'array', 'required_without:ingredients'],
            'components.*.product_id' => [
                "exclude_unless:stock_mode,{$recipe}", 'required', 'integer', 'distinct',
                Rule::exists('products', 'id'),
            ],
            'components.*.quantity' => ["exclude_unless:stock_mode,{$recipe}", 'required', 'numeric', 'gt:0', 'max:9999'],
        ];
    }

    /** Reject components that would make a product contain itself, directly or through another product. */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $product = $this->route('product');

                if (! $product instanceof Product || $this->input('stock_mode') !== ProductStockMode::Recipe->value) {
                    return; // A brand-new product can't be inside anything yet, so it can't form a loop.
                }

                $forbidden = [$product->id, ...app(ProductCatalogService::class)->ancestorIds($product)];

                foreach ($this->input('components', []) as $index => $line) {
                    if (in_array((int) ($line['product_id'] ?? 0), $forbidden, true)) {
                        $validator->errors()->add(
                            "components.{$index}.product_id",
                            __('":name" can\'t contain this product, because it would end up inside itself.', ['name' => $product->name]),
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'ingredients.required_without' => __('Add at least one ingredient or product to the recipe.'),
            'components.required_without' => __('Add at least one ingredient or product to the recipe.'),
            'inventory_item_id.required' => __('Choose the inventory item this product deducts.'),
            'inventory_item_id.exists' => __('Choose an inventory item of type "Standalone product".'),
            'ingredients.*.inventory_item_id.distinct' => __('Each ingredient can appear only once in a recipe.'),
            'components.*.product_id.distinct' => __('Each product can appear only once; increase its quantity instead.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'saipos_sku_id' => __('Saipos SKU'),
            'name' => __('name'),
            'price' => __('price'),
            'target_margin_percent' => __('target margin'),
            'stock_mode' => __('stock mode'),
            'inventory_item_id' => __('standalone item'),
            'ingredients' => __('ingredients'),
            'components' => __('products inside'),
            'ingredients.*.inventory_item_id' => __('ingredient'),
            'ingredients.*.quantity_needed' => __('quantity'),
            'components.*.product_id' => __('product'),
            'components.*.quantity' => __('quantity'),
        ];
    }

    /** @param  list<string>  $fields */
    private function withoutBlankRows(string $key, array $fields): array
    {
        return collect($this->input($key, []))
            ->filter(fn ($line): bool => is_array($line) && collect($fields)->contains(fn (string $f): bool => filled($line[$f] ?? null)))
            ->values()
            ->all();
    }
}
