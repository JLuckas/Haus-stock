<x-tabs :label="__('Finance')" :tabs="[
    ['href' => route('reports.pnl'), 'label' => __('Results'), 'active' => request()->routeIs('reports.pnl')],
    ['href' => route('reports.profitability'), 'label' => __('Product margins'), 'active' => request()->routeIs('reports.profitability')],
]" />
