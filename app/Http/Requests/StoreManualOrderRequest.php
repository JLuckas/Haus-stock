<?php

namespace App\Http\Requests;

use App\Exceptions\Stock\InvalidRecipeException;
use App\Models\Product;
use App\Services\Stock\BillOfMaterials;
use App\Services\Stock\ManualOrderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManualOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference' => filled($this->input('reference')) ? trim((string) $this->input('reference')) : null,
            // Drop product rows the user added but left empty.
            'items' => collect($this->input('items', []))
                ->filter(fn ($line): bool => is_array($line) && filled($line['product_id'] ?? null))
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
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Every product must resolve to stock (recipe, combo or standalone item) before anything is saved. */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $billOfMaterials = app(BillOfMaterials::class);
                $products = Product::query()->whereKey(array_column($this->input('items'), 'product_id'))->get()->keyBy('id');

                foreach ($this->input('items') as $index => $line) {
                    try {
                        $billOfMaterials->perUnit($products[(int) $line['product_id']]);
                    } catch (InvalidRecipeException $e) {
                        $validator->errors()->add("items.{$index}.product_id", $e->getMessage());
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
            'items.*.product_id.distinct' => __('Each product can appear only once; increase its quantity instead.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'reference' => __('Saipos order number'),
            'items' => __('products'),
            'items.*.product_id' => __('product'),
            'items.*.quantity' => __('quantity'),
            'notes' => __('notes'),
        ];
    }
}
