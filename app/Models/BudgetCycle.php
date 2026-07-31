<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCycle extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'budget_id',
        'user_id',
        'account_id',
        'currency',
        'budget_name',
        'starts_at',
        'ends_at',
        'closed_at',
        'is_backfilled',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_date',
            'ends_at' => 'immutable_date',
            'closed_at' => 'immutable_datetime',
            'is_backfilled' => 'boolean',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetCycleItem::class);
    }

    public function scopeClosedBefore(Builder $query, string $date): Builder
    {
        return $query->whereDate('ends_at', '<', $date);
    }

    public function getTotalBudgetedAttribute(): float
    {
        return $this->items->sum(fn (BudgetCycleItem $item) => $item->cap_amount);
    }

    /**
     * Every category id the snapshot tracked, including the children each item
     * covered when the cycle closed.
     *
     * @return array<int, int>
     */
    public function trackedCategoryIds(): array
    {
        return $this->items
            ->flatMap(fn (BudgetCycleItem $item) => $item->category_ids)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
