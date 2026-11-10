<x-tabs :label="__('Products')" :tabs="[
    ['href' => route('products.index'), 'label' => __('Catalog'), 'active' => request()->routeIs('products.index')],
    ['href' => route('products.costs'), 'label' => __('Costs'), 'active' => request()->routeIs('products.costs')],
]" />
