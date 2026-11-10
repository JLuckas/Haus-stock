<?php

namespace App\Enums;

enum EmployeeRole: string
{
    case Kitchen = 'kitchen';
    case Delivery = 'delivery';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Kitchen => __('Kitchen'),
            self::Delivery => __('Delivery rider'),
            self::Cashier => __('Cashier'),
        };
    }
}
