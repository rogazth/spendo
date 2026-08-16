<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\User;
use App\Services\InsightsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InsightsController extends Controller
{
    private const CYCLE_OPTIONS = 24;

    public function __construct(private readonly InsightsService $insights) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $now = CarbonImmutable::now()->startOfDay();

        $years = $this->insights->availableYears($user, $now);
        $selectedYear = (int) $request->integer('year', $now->year);

        if (! in_array($selectedYear, $years, true)) {
            $selectedYear = $now->year;
        }

        $cycles = array_reverse($this->insights->cyclePeriods($user, $now, self::CYCLE_OPTIONS));
        $selectedCycle = $this->resolveCycle($user, $request->string('cycle')->toString(), $cycles, $now);

        return Inertia::render('insights', [
            'years' => $years,
            'selectedYear' => $selectedYear,
            'year' => fn () => $this->yearSections($user, $selectedYear, $now),
            'cycleOptions' => array_map(fn (array $cycle) => [
                'start' => $cycle['start']->toDateString(),
                'end' => $cycle['end']->toDateString(),
                'is_current' => $cycle['start']->equalTo($selectedCycle['current']['start']),
            ], $cycles),
            'selectedCycle' => [
                'start' => $selectedCycle['start']->toDateString(),
                'end' => $selectedCycle['end']->toDateString(),
                'is_current' => $selectedCycle['start']->equalTo($selectedCycle['current']['start']),
                'days_left' => (int) max(0, $now->diffInDays($selectedCycle['end'], false)),
            ],
            'cycle' => fn () => $this->cycleSections($user, $selectedCycle, $now),
        ]);
    }

    /**
     * Year to date per currency: the cycle-by-cycle series that feeds the chart
     * plus the category ranking for the same span.
     *
     * @return list<array<string, mixed>>
     */
    private function yearSections(User $user, int $year, CarbonImmutable $now): array
    {
        $periods = $this->insights->yearPeriods($user, $year, $now);

        if ($periods === []) {
            return [];
        }

        $start = $periods[0]['start'];
        $end = $periods[count($periods) - 1]['end'];
        $cashFlow = $this->insights->cashFlowByPeriod($user, $periods);

        // Same number of cycles a year earlier, so a year in progress compares
        // against the same stretch of the previous one.
        $comparePeriods = array_slice(
            $this->insights->yearPeriods($user, $year - 1, $now),
            0,
            count($periods),
        );
        $breakdown = $comparePeriods === []
            ? $this->insights->categoryBreakdown($user, $start, $end)
            : $this->insights->categoryBreakdown(
                $user,
                $start,
                $end,
                $comparePeriods[0]['start'],
                $comparePeriods[count($comparePeriods) - 1]['end'],
            );

        return collect(array_keys($cashFlow))
            ->merge(array_keys($breakdown))
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $currency) use ($cashFlow, $breakdown, $periods, $start, $end) {
                $points = $cashFlow[$currency] ?? [];
                $income = round(array_sum(array_column($points, 'income')), 2);
                $expense = round(array_sum(array_column($points, 'expense')), 2);

                return [
                    'currency' => $currency,
                    'currency_locale' => Currency::localeFor($currency),
                    'cash_flow' => $points,
                    'income' => $income,
                    'expense' => $expense,
                    'net' => round($income - $expense, 2),
                    'average_expense' => round($expense / count($periods), 2),
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'categories' => $breakdown[$currency] ?? ['total' => 0, 'rows' => []],
                ];
            })
            ->all();
    }

    /**
     * The selected cycle per currency: totals, category ranking against the
     * previous cycle, and the biggest hits.
     *
     * @param  array{start: CarbonImmutable, end: CarbonImmutable, previous: array{start: CarbonImmutable, end: CarbonImmutable}}  $cycle
     * @return list<array<string, mixed>>
     */
    private function cycleSections(User $user, array $cycle, CarbonImmutable $now): array
    {
        $totals = $this->insights->totalsFor($user, $cycle['start'], $cycle['end']);
        $breakdown = $this->insights->categoryBreakdown(
            $user,
            $cycle['start'],
            $cycle['end'],
            $cycle['previous']['start'],
            $cycle['previous']['end'],
        );
        $largest = $this->insights->largestExpenses($user, $cycle['start'], $cycle['end']);

        return collect(array_keys($totals))
            ->merge(array_keys($breakdown))
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $currency) => [
                'currency' => $currency,
                'currency_locale' => Currency::localeFor($currency),
                'income' => $totals[$currency]['income'] ?? 0,
                'expense' => $totals[$currency]['expense'] ?? 0,
                'net' => $totals[$currency]['net'] ?? 0,
                'categories' => $breakdown[$currency] ?? ['total' => 0, 'rows' => []],
                'largest_expenses' => $largest[$currency] ?? [],
            ])
            ->all();
    }

    /**
     * The cycle asked for in the querystring, falling back to the one in
     * progress, together with the cycle before it for comparison.
     *
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $cycles
     * @return array{start: CarbonImmutable, end: CarbonImmutable, previous: array{start: CarbonImmutable, end: CarbonImmutable}, current: array{start: CarbonImmutable, end: CarbonImmutable}}
     */
    private function resolveCycle(User $user, string $requested, array $cycles, CarbonImmutable $now): array
    {
        $current = $cycles[0];
        $selected = collect($cycles)
            ->first(fn (array $cycle) => $cycle['start']->toDateString() === $requested)
            ?? $current;

        [$previousStart, $previousEnd] = User::resolveMonthlyCycleForDay(
            $selected['start']->subDay(),
            (int) ($user->settings?->budget_cycle_start_day ?? 1),
        );

        return [
            'start' => $selected['start'],
            'end' => $selected['end'],
            'previous' => ['start' => $previousStart, 'end' => $previousEnd],
            'current' => $current,
        ];
    }
}
