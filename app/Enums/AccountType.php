<?php

namespace App\Enums;

enum AccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Cash = 'cash';
    case Investment = 'investment';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Cuenta Corriente',
            self::Savings => 'Cuenta de Ahorro',
            self::Cash => 'Efectivo',
            self::Investment => 'Inversión',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Checking => 'building-library',
            self::Savings => 'piggy-bank',
            self::Cash => 'banknotes',
            self::Investment => 'chart-bar',
        };
    }
}
