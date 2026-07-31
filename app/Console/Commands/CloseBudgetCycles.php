<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BudgetCycleService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CloseBudgetCycles extends Command
{
    protected $signature = 'budgets:close-cycles {--lookback=1 : Cycles to walk back per budget}';

    protected $description = 'Snapshot the caps of every budget cycle that has already closed';

    public function handle(BudgetCycleService $cycles): int
    {
        $now = CarbonImmutable::now()->startOfDay();
        $lookback = max(1, (int) $this->option('lookback'));
        $created = 0;

        User::query()->each(function (User $user) use ($cycles, $now, $lookback, &$created): void {
            $created += $cycles->materializeClosedCycles($user, $now, $lookback);
        });

        $this->info("Ciclos cerrados: {$created}");

        return self::SUCCESS;
    }
}
