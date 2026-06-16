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
        'emoji',
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

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Account balance is the signed sum of all transactions on this account.
     * Positive amounts = inflows; negative amounts = outflows.
     */
    public function getCurrentBalanceAttribute(): float
    {
        $balanceInCents = (int) ($this->transactions()
            ->selectRaw('COALESCE(SUM(amount), 0) as balance')
            ->value('balance') ?? 0);

        return $balanceInCents / 100;
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->current_balance, 0, ',', '.');
    }
}
