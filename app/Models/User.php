<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUuid, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSettings::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function activeAccounts(): HasMany
    {
        return $this->accounts()->where('is_active', true);
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function resolveCurrentCycleRange(CarbonImmutable $reference): array
    {
        return self::resolveMonthlyCycleForDay(
            $reference,
            (int) ($this->settings?->budget_cycle_start_day ?? 1),
        );
    }

    /**
     * Resolve the monthly cycle [start, end] that contains $reference for a given
     * start day-of-month. Days that do not exist in a month (e.g. 29 in February,
     * 31 in April) fall back to that month's last day.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public static function resolveMonthlyCycleForDay(CarbonImmutable $reference, int $day): array
    {
        $reference = $reference->startOfDay();
        $startDayThisMonth = min($day, $reference->daysInMonth);

        if ($reference->day >= $startDayThisMonth) {
            $cycleStart = $reference->setDay($startDayThisMonth);
        } else {
            $previousMonth = $reference->subMonthNoOverflow();
            $cycleStart = $previousMonth->setDay(min($day, $previousMonth->daysInMonth));
        }

        $nextMonth = $cycleStart->addMonthNoOverflow();
        $cycleEnd = $nextMonth->setDay(min($day, $nextMonth->daysInMonth))->subDay();

        return [$cycleStart, $cycleEnd];
    }

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);

        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1).substr($words[1], 0, 1));
        }

        return strtoupper(substr($this->name, 0, 2));
    }
}
