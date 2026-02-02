<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\AccountType;
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
        'type',
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
            'type' => AccountType::class,
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'linked_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getCurrentBalanceAttribute(): float
    {
        $balanceInCents = $this->transactions()
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN type IN ('income', 'transfer_in') THEN amount
                        WHEN type IN ('expense', 'transfer_out', 'settlement') THEN -amount
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
