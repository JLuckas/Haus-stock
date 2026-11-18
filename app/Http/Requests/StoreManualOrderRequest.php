<?php

namespace App\Http\Requests;

use App\Exceptions\Stock\InvalidRecipeException;
use App\Models\Product;
use App\Services\Stock\BillOfMaterials;
use App\Services\Stock\ManualOrderService;
use App\Services\Stock\OrderCustomizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManualOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference' => filled($this->input('reference')) ? trim((string) $this->input('reference')) : null,
            // Drop product rows the user added but left empty, and changes left half-filled.
            'items' => collect($this->input('items', []))
                ->filter(fn ($line): bool => is_array($line) && filled($line['product_id'] ?? null))
                ->map(fn (array $line): array => [
                    ...$line,
                    'unit_price' => filled($line['unit_price'] ?? null) ? $line['unit_price'] : null,
                    'customizations' => collect($line['customizations'] ?? [])
                        ->filter(fn ($c): bool => is_array($c) && filled($c['type'] ?? null) && (filled($c['to'] ?? null) || filled($c['item'] ?? null) || filled($c['product'] ?? null)))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'reference' => [
                'nullable', 'string', 'max:100',
                'not_regex:/^'.preg_quote(ManualOrderService::REFERENCE_PREFIX, '/').'/i',
                Rule::unique('orders', 'saipos_order_id'),
            ],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            // The same product can appear twice with different changes (one with, one without onion).
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999'],
            // Price charged per unit when it differs from the product's (e.g. extras charged).
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'items.*.customizations' => ['array', 'max:30'],
            'items.*.customizations.*.type' => ['required', Rule::in(OrderCustomizer::TYPES)],
            // Ids of what is swapped / removed / added (checked against the product in after()).
            'items.*.customizations.*.from' => ['nullable', 'integer'],
            'items.*.customizations.*.to' => ['nullable', 'integer'],
            'items.*.customizations.*.item' => ['nullable', 'integer'],
            'items.*.customizations.*.product' => ['nullable', 'integer'],
            'items.*.customizations.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Every product must resolve to stock (recipe, combo or standalone item) before anything is
     * saved, and its changes must make sense (swap within the same category, etc.).
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $billOfMaterials = app(BillOfMaterials::class);
                $customizer = app(OrderCustomizer::class);
                $products = Product::query()->whereKey(array_column($this->input('items'), 'product_id'))->get()->keyBy('id');

                foreach ($this->input('items') as $index => $line) {
                    $product = $products[(int) $line['product_id']];

                    try {
                        $billOfMaterials->perUnit($product);
                    } catch (InvalidRecipeException $e) {
                        $validator->errors()->add("items.{$index}.product_id", $e->getMessage());

                        continue;
                    }

                    foreach ($customizer->errors($product, $line['customizations'] ?? []) as $error) {
                        $validator->errors()->add("items.{$index}.customizations", $error);
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => __('Add at least one product to the order.'),
            'reference.unique' => __('An order with this number is already registered.'),
            'reference.not_regex' => __('Numbers starting with ":prefix" are reserved for manual orders.', ['prefix' => ManualOrderService::REFERENCE_PREFIX]),
        ];
    }

    public function attributes(): array
    {
        return [
            'reference' => __('Saipos order number'),
            'items' => __('products'),
            'items.*.product_id' => __('product'),
            'items.*.quantity' => __('quantity'),
            'items.*.unit_price' => __('price'),
            'notes' => __('notes'),
        ];
    }
}
