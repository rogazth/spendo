<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\TransactionType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'linked_transaction_id',
        'type',
        'amount',
        'currency',
        'description',
        'notes',
        'exclude_from_budget',
        'transaction_date',
    ];

    protected $attributes = [
        'type' => TransactionType::Regular->value,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'type' => TransactionType::class,
            'exclude_from_budget' => 'boolean',
            'transaction_date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function linkedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'linked_transaction_id');
    }

    public function counterpartTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'linked_transaction_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'transaction_tag');
    }

    public function scopeBudgetEligible(Builder $query): Builder
    {
        return $query
            ->where('amount', '<', 0)
            ->where('type', TransactionType::Regular)
            ->where('exclude_from_budget', false);
    }

    public function scopeForBudget(Builder $query, Budget $budget): Builder
    {
        $categoryIds = $budget->budgetCategoryIds();
        $accountIds = $budget->accountIds();

        $query->where('currency', $budget->currency);

        if ($categoryIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereIn('category_id', $categoryIds);

        // Budgets draw from an explicit set of accounts. A budget with no
        // associated accounts falls back to all accounts in its currency, which
        // preserves legacy behaviour for budgets created before account scoping.
        if ($accountIds !== []) {
            $query->whereIn('account_id', $accountIds);
        }

        return $query;
    }

    public function scopeWithinDateRange(
        Builder $query,
        CarbonInterface|string|null $startDate,
        CarbonInterface|string|null $endDate,
    ): Builder {
        if ($startDate !== null) {
            $query->whereDate('transaction_date', '>=', $startDate instanceof CarbonInterface ? $startDate->toDateString() : $startDate);
        }

        if ($endDate !== null) {
            $query->whereDate('transaction_date', '<=', $endDate instanceof CarbonInterface ? $endDate->toDateString() : $endDate);
        }

        return $query;
    }

    public function scopeForBudgetSpending(
        Builder $query,
        Budget $budget,
        CarbonInterface|string|null $startDate = null,
        CarbonInterface|string|null $endDate = null,
    ): Builder {
        return $query
            ->budgetEligible()
            ->forBudget($budget)
            ->withinDateRange($startDate, $endDate);
    }

    /**
     * Get and set the amount (stored as signed cents in DB).
     * Positive = inflow to account; negative = outflow.
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->amount < 0 ? '-' : '+';

        return $prefix.'$'.number_format(abs($this->amount), 0, ',', '.');
    }

    public function isTransfer(): bool
    {
        return $this->type === TransactionType::Transfer;
    }

    public function isInitialBalance(): bool
    {
        return $this->type === TransactionType::InitialBalance;
    }

    public function isRegular(): bool
    {
        return $this->type === TransactionType::Regular;
    }

    public function isIncome(): bool
    {
        return $this->isRegular() && $this->amount > 0;
    }

    public function isExpense(): bool
    {
        return $this->isRegular() && $this->amount < 0;
    }
}
