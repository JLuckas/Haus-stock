<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Produtos → Categorias: ingredient categories (Pão, Carne, Queijo…) and product categories
 * (Box, Hambúrguer, Bebida…). On an order, swaps stay within the same category.
 */
class CategoryController extends Controller
{
    public function index(): View
    {
        return view('products.categories', [
            'ingredientCategories' => Category::query()->forIngredients()->withCount('inventoryItems')->with('inventoryItems:id,name,category_id')->get(),
            'productCategories' => Category::query()->forProducts()->withCount('products')->with('products:id,name,category_id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $category = Category::query()->create($data + [
            'position' => (int) Category::query()->where('scope', $data['scope'])->max('position') + 1,
        ]);

        return back()->with('success', __('Category :name created.', ['name' => $category->name]));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return back()->with('success', __('Category :name saved.', ['name' => $category->name]));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete(); // items and products in it become uncategorized

        return back()->with('success', __('Category :name deleted. Its items now have no category.', ['name' => $category->name]));
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $scope = $category?->scope ?? $request->input('scope');
        $request->merge(['slug' => Str::slug((string) $request->input('name'))]);

        $data = $request->validate([
            'scope' => $category ? ['prohibited'] : ['required', Rule::in([Category::INGREDIENT, Category::PRODUCT])],
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', Rule::unique('categories', 'slug')->where('scope', $scope)->ignore($category)],
            'color' => ['nullable', Rule::in(Tag::COLORS)],
        ], [
            'slug.unique' => __('There is already a category with this name.'),
            'slug.required' => __('Type a name with letters or numbers.'),
        ], ['name' => __('name')]);

        $data['name'] = trim($data['name']);
        $data['color'] ??= $category?->color ?? 'gray';

        return $data;
    }
}
