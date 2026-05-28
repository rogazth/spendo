<?php

namespace App\Enums;

enum TransactionType: string
{
    case Regular = 'regular';
    case Transfer = 'transfer';
    case InitialBalance = 'initial_balance';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Transfer => 'Transferencia',
            self::InitialBalance => 'Balance inicial',
        };
    }
}
