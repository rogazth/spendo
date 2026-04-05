<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function getTotalBudgetedAttribute(): float
    {
        $totalInCents = $this->items()->sum('amount');

        return $totalInCents / 100;
    }

    /**
     * Resolve the current (or reference-relative) cycle start and end dates.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function resolveCycleRange(CarbonImmutable $referenceDate): array
    {
        $anchorDate = CarbonImmutable::parse($this->anchor_date)->startOfDay();
        $effectiveReference = $referenceDate->startOfDay();
        $budgetEndDate = $this->ends_at
            ? CarbonImmutable::parse($this->ends_at)->startOfDay()
            : null;

        if ($budgetEndDate !== null && $effectiveReference->greaterThan($budgetEndDate)) {
            $effectiveReference = $budgetEndDate;
        }

        if ($effectiveReference->lessThan($anchorDate)) {
            $effectiveReference = $anchorDate;
        }

        if (in_array($this->frequency, ['weekly', 'biweekly'], true)) {
            $stepInDays = $this->frequency === 'weekly' ? 7 : 14;
            $daysSinceAnchor = max(0, $anchorDate->diffInDays($effectiveReference, false));
            $cycleIndex = intdiv($daysSinceAnchor, $stepInDays);
            $cycleStart = $anchorDate->addDays($cycleIndex * $stepInDays);
            $cycleEnd = $cycleStart->addDays($stepInDays - 1);
        } else {
            $stepInMonths = $this->frequency === 'bimonthly' ? 2 : 1;
            $monthsSinceAnchor = max(0, $anchorDate->diffInMonths($effectiveReference, false));
            $cycleIndex = intdiv($monthsSinceAnchor, $stepInMonths);
            $cycleStart = $anchorDate->addMonthsNoOverflow($cycleIndex * $stepInMonths);
            $cycleEnd = $cycleStart->addMonthsNoOverflow($stepInMonths)->subDay();
        }

        if ($budgetEndDate !== null && $cycleEnd->greaterThan($budgetEndDate)) {
            $cycleEnd = $budgetEndDate;
        }

        return [$cycleStart, $cycleEnd];
    }
}
