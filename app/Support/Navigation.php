<?php

namespace App\Support;

use App\Enums\OrderStockState;
use App\Models\InventoryItem;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

/**
 * The app's sections, in one place: the sidebar, the breadcrumb in the top bar and the
 * command palette (Ctrl/⌘ K) are all built from here.
 */
final class Navigation
{
    /**
     * @return list<array{label: string, items: list<array{label: string, href: string, icon: string, active: bool, badge: ?int, badgeTone: string}>}>
     */
    public static function groups(): array
    {
        // Built once per request (the layout asks several times).
        if (request()->attributes->has('navigation')) {
            return request()->attributes->get('navigation');
        }

        $groups = [
            [
                'label' => __('Overview'),
                'items' => [
                    self::item(__('Dashboard'), 'dashboard', 'home', ['dashboard']),
                ],
            ],
            [
                'label' => __('Operations'),
                'items' => [
                    self::item(__('Orders'), 'orders.index', 'orders', ['orders.*'], self::failedOrders(), 'red'),
                    self::item(__('Inventory'), 'inventory.index', 'inventory', ['inventory.*'], self::lowStock(), 'amber'),
                    self::item(__('Products'), 'products.index', 'products', ['products.index', 'products.create', 'products.edit']),
                    self::item(__('Deductions'), 'manual-deductions.index', 'deduct', ['manual-deductions.*']),
                ],
            ],
            [
                'label' => __('Finance'),
                'items' => [
                    self::item(__('Results'), 'reports.pnl', 'chart', ['reports.pnl']),
                    self::item(__('Expenses'), 'expenses', 'expenses', ['expenses', 'expenses.*']),
                    self::item(__('Product margins'), 'reports.profitability', 'percent', ['reports.profitability']),
                    self::item(__('Product costs'), 'products.costs', 'calculator', ['products.costs']),
                ],
            ],
        ];

        request()->attributes->set('navigation', $groups);

        return $groups;
    }

    /** "Create" shortcuts for the top bar's New menu and the command palette. */
    public static function quickActions(): array
    {
        return [
            ['label' => __('New order'), 'href' => route('orders.create'), 'icon' => 'orders'],
            ['label' => __('Deduct stock'), 'href' => route('manual-deductions.create'), 'icon' => 'deduct'],
            ['label' => __('Register a day payment'), 'href' => route('expenses.index', 'personnel').'#expense-form', 'icon' => 'expenses'],
            ['label' => __('Register an expense'), 'href' => route('expenses.index', 'general').'#expense-form', 'icon' => 'expenses'],
            ['label' => __('Add product'), 'href' => route('products.create'), 'icon' => 'products'],
            ['label' => __('New inventory item'), 'href' => route('inventory.create'), 'icon' => 'inventory'],
        ];
    }

    /** The section the current page belongs to: [group label, item label], for the breadcrumb. */
    public static function current(): ?array
    {
        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                if ($item['active']) {
                    return [$group['label'], $item['label'], $item['href']];
                }
            }
        }

        return null;
    }

    /** Everything the command palette can jump to. */
    public static function commands(): array
    {
        $commands = [];

        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                $commands[] = ['label' => $item['label'], 'group' => __('Go to'), 'href' => $item['href'], 'icon' => 'arrow'];
            }
        }

        foreach (self::quickActions() as $action) {
            $commands[] = ['label' => $action['label'], 'group' => __('Create'), 'href' => $action['href'], 'icon' => 'plus'];
        }

        $commands[] = ['label' => __('Profile'), 'group' => __('Account'), 'href' => route('profile.edit'), 'icon' => 'arrow'];

        return $commands;
    }

    /** @param  list<string>  $patterns */
    private static function item(string $label, string $route, string $icon, array $patterns, ?int $badge = null, string $badgeTone = 'gray'): array
    {
        return [
            'label' => $label,
            'href' => route($route),
            'icon' => $icon,
            'active' => Route::currentRouteNamed(...$patterns),
            'badge' => $badge ?: null,
            'badgeTone' => $badgeTone,
        ];
    }

    private static function failedOrders(): int
    {
        return Order::query()->inStockState(OrderStockState::Failed)->count();
    }

    private static function lowStock(): int
    {
        return InventoryItem::query()->belowMinimum()->count();
    }
}
