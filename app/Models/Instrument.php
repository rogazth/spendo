<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\InstrumentType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instrument extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'currency',
        'credit_limit',
        'billing_cycle_day',
        'payment_due_day',
        'color',
        'icon',
        'last_four_digits',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => InstrumentType::class,
            'credit_limit' => 'integer',
            'billing_cycle_day' => 'integer',
            'payment_due_day' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function outgoingSettlements(): HasMany
    {
        return $this->hasMany(Transaction::class, 'from_instrument_id');
    }

    /**
     * Get and set the credit limit (stored as cents in DB).
     */
    protected function creditLimit(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? $value / 100 : null,
            set: fn ($value) => $value !== null ? (int) round($value * 100) : null,
        );
    }

    public function isCreditCard(): bool
    {
        return $this->type->isCreditCard();
    }

    /**
     * Outstanding debt for credit cards: sum of expenses - sum of settlements paid to this card.
     */
    public function getCurrentDebtAttribute(): float
    {
        if (! $this->isCreditCard()) {
            return 0;
        }

        $debtInCents = $this->transactions()
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN type = 'expense' THEN amount
                        WHEN type = 'settlement' THEN -amount
                        ELSE 0
                    END
                ), 0) as debt
            ")
            ->value('debt') ?? 0;

        return $debtInCents / 100;
    }

    /**
     * Physical balance for bank instruments: income deposits - expenses - settlements paid out.
     */
    public function getCurrentBalanceAttribute(): float
    {
        if ($this->isCreditCard()) {
            return -$this->current_debt;
        }

        $balanceInCents = $this->transactions()
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN type IN ('income', 'transfer_in') THEN amount
                        WHEN type IN ('expense', 'transfer_out') THEN -amount
                        ELSE 0
                    END
                ), 0) as balance
            ")
            ->value('balance') ?? 0;

        // Subtract settlements paid from this instrument
        $settlementsOut = $this->outgoingSettlements()
            ->where('type', 'settlement')
            ->sum('amount');

        return ($balanceInCents - $settlementsOut) / 100;
    }

    public function getAvailableCreditAttribute(): ?float
    {
        if (! $this->isCreditCard() || $this->credit_limit === null) {
            return null;
        }

        return $this->credit_limit - $this->current_debt;
    }

    public function getFormattedDebtAttribute(): string
    {
        return number_format($this->current_debt, 0, ',', '.');
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->current_balance, 0, ',', '.');
    }
}
