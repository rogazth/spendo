<?php

namespace App\Enums;

enum TransactionType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Settlement = 'settlement';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Gasto',
            self::Income => 'Ingreso',
            self::TransferOut => 'Transferencia Saliente',
            self::TransferIn => 'Transferencia Entrante',
            self::Settlement => 'Liquidación TDC',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Expense => 'arrow-up',
            self::Income => 'arrow-down',
            self::TransferOut => 'arrow-right',
            self::TransferIn => 'arrow-left',
            self::Settlement => 'credit-card',
        };
    }

    public function isDebit(): bool
    {
        return match ($this) {
            self::Expense, self::TransferOut, self::Settlement => true,
            default => false,
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::Income, self::TransferIn => true,
            default => false,
        };
    }
}
