<?php

namespace App\Enums;

enum InstrumentType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Cash = 'cash';
    case Investment = 'investment';
    case CreditCard = 'credit_card';
    case PrepaidCard = 'prepaid_card';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Cuenta Corriente',
            self::Savings => 'Cuenta de Ahorro',
            self::Cash => 'Efectivo',
            self::Investment => 'Inversión',
            self::CreditCard => 'Tarjeta de Crédito',
            self::PrepaidCard => 'Tarjeta Prepago',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Checking => 'building-library',
            self::Savings => 'piggy-bank',
            self::Cash => 'banknotes',
            self::Investment => 'chart-bar',
            self::CreditCard => 'credit-card',
            self::PrepaidCard => 'credit-card',
        };
    }

    public function isCreditCard(): bool
    {
        return $this === self::CreditCard || $this === self::PrepaidCard;
    }
}
