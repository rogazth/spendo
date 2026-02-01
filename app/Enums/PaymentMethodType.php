<?php

namespace App\Enums;

enum PaymentMethodType: string
{
    case CreditCard = 'credit_card';
    case DebitCard = 'debit_card';
    case PrepaidCard = 'prepaid_card';
    case Cash = 'cash';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::CreditCard => 'Tarjeta de Crédito',
            self::DebitCard => 'Tarjeta de Débito',
            self::PrepaidCard => 'Tarjeta Prepago',
            self::Cash => 'Efectivo',
            self::Transfer => 'Transferencia',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CreditCard => 'credit-card',
            self::DebitCard => 'credit-card',
            self::PrepaidCard => 'credit-card',
            self::Cash => 'banknotes',
            self::Transfer => 'arrows-right-left',
        };
    }

    public function isDebit(): bool
    {
        return match ($this) {
            self::DebitCard, self::PrepaidCard, self::Cash, self::Transfer => true,
            default => false,
        };
    }

    public function requiresLinkedAccount(): bool
    {
        return match ($this) {
            self::DebitCard, self::PrepaidCard, self::Cash, self::Transfer => true,
            self::CreditCard => false,
        };
    }
}
