<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'currency',
        'frequency',
        'anchor_date',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'anchor_date' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class);
    }

    /**
     * @return array<int, int>
     */
    public function accountIds(): array
    {
        return $this->relationLoaded('accounts')
            ? $this->accounts->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $this->accounts()->pluck('accounts.id')->map(fn ($id) => (int) $id)->all();
    }

    public function getTotalBudgetedAttribute(): float
    {
        $totalInCents = $this->items()->sum('amount');

        return $totalInCents / 100;
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function budgetCategoryGroups(): array
    {
        return $this->items->mapWithKeys(function (BudgetItem $item): array {
            $category = $item->category;

            if (! $category) {
                return [$item->id => []];
            }

            $categoryIds = [$category->id];
            $childrenIds = $category->relationLoaded('children')
                ? $category->children->pluck('id')->all()
                : $category->children()->pluck('id')->all();

            return [$item->id => array_values(array_unique(array_merge($categoryIds, $childrenIds)))];
        })->all();
    }

    /**
     * @return array<int, int>
     */
    public function budgetCategoryIds(): array
    {
        return collect($this->budgetCategoryGroups())
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve the current (or reference-relative) cycle start and end dates.
     *
     * Monthly budgets follow the user's global cycle start day rather than the
     * anchor's day-of-month; pass $monthlyCycleStartDay to avoid loading the
     * user relation in a loop. The anchor still applies to the other frequencies.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function resolveCycleRange(CarbonImmutable $referenceDate, ?int $monthlyCycleStartDay = null): array
    {
        $effectiveReference = $referenceDate->startOfDay();
        $budgetEndDate = $this->ends_at
            ? CarbonImmutable::parse($this->ends_at)->startOfDay()
            : null;

        if ($budgetEndDate !== null && $effectiveReference->greaterThan($budgetEndDate)) {
            $effectiveReference = $budgetEndDate;
        }

        if ($this->frequency === 'monthly') {
            $day = $monthlyCycleStartDay ?? (int) ($this->user?->settings?->budget_cycle_start_day ?? 1);
            [$cycleStart, $cycleEnd] = User::resolveMonthlyCycleForDay($effectiveReference, $day);

            if ($budgetEndDate !== null && $cycleEnd->greaterThan($budgetEndDate)) {
                $cycleEnd = $budgetEndDate;
            }

            return [$cycleStart, $cycleEnd];
        }

        $anchorDate = CarbonImmutable::parse($this->anchor_date)->startOfDay();

        if ($effectiveReference->lessThan($anchorDate)) {
            $effectiveReference = $anchorDate;
        }

        if (in_array($this->frequency, ['weekly', 'biweekly'], true)) {
            $stepInDays = $this->frequency === 'weekly' ? 7 : 14;
            $daysSinceAnchor = (int) floor(max(0, $anchorDate->diffInDays($effectiveReference, false)));
            $cycleIndex = intdiv($daysSinceAnchor, $stepInDays);
            $cycleStart = $anchorDate->addDays($cycleIndex * $stepInDays);
            $cycleEnd = $cycleStart->addDays($stepInDays - 1);
        } else {
            $stepInMonths = 2;
            $monthsSinceAnchor = (int) floor(max(0, $anchorDate->diffInMonths($effectiveReference, false)));
            $cycleIndex = intdiv($monthsSinceAnchor, $stepInMonths);
            $cycleStart = $anchorDate->addMonthsNoOverflow($cycleIndex * $stepInMonths);
            $cycleEnd = $anchorDate->addMonthsNoOverflow(($cycleIndex + 1) * $stepInMonths)->subDay();
        }

        if ($budgetEndDate !== null && $cycleEnd->greaterThan($budgetEndDate)) {
            $cycleEnd = $budgetEndDate;
        }

        return [$cycleStart, $cycleEnd];
    }
}
