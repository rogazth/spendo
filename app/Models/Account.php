<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'currency',
        'color',
        'icon',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
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

    /**
     * Account balance = income + transfer_in - expense - transfer_out.
     * Settlements do NOT affect account balance (recognized at purchase time).
     */
    public function getCurrentBalanceAttribute(): float
    {
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

        return $balanceInCents / 100;
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->current_balance, 0, ',', '.');
    }
}
