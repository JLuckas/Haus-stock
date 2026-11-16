{{-- Expenses section tabs. $active: personnel | bill | supplier | general | series | tags; $period (optional) keeps the chosen period. --}}
@php
    $q = isset($period) ? $period->query() : [];
@endphp

<x-tabs :label="__('Expenses')" :tabs="[
    ['href' => route('expenses.index', ['kind' => 'personnel'] + $q), 'label' => __('Personnel'), 'active' => $active === 'personnel', 'count' => $counts['personnel']],
    ['href' => route('expenses.index', ['kind' => 'bill'] + $q), 'label' => __('Bills'), 'active' => $active === 'bill', 'count' => $counts['bill']],
    ['href' => route('expenses.index', ['kind' => 'supplier'] + $q), 'label' => __('Suppliers'), 'active' => in_array($active, ['supplier', 'suppliers'], true), 'count' => $counts['supplier']],
    ['href' => route('expenses.index', ['kind' => 'general'] + $q), 'label' => __('General expenses'), 'active' => $active === 'general', 'count' => $counts['general']],
    ['href' => route('expenses.series.index'), 'label' => __('Recurring'), 'active' => $active === 'series', 'count' => $counts['series']],
    ['href' => route('expenses.tags.index'), 'label' => __('Tags'), 'active' => $active === 'tags', 'count' => $counts['tags']],
]" />
