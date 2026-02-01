<?php

namespace App\Enums;

enum CategoryType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Gasto',
            self::Income => 'Ingreso',
            self::System => 'Sistema',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Expense => '#EF4444',
            self::Income => '#10B981',
            self::System => '#6B7280',
        };
    }
}
