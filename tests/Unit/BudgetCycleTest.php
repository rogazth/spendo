<?php

use App\Models\Budget;
use Carbon\CarbonImmutable;

test('monthly budget cycle ignores the anchor day and uses the provided cycle start day', function () {
    $budget = new Budget([
        'frequency' => 'monthly',
        'anchor_date' => '2026-01-03',
        'ends_at' => null,
    ]);

    [$start, $end] = $budget->resolveCycleRange(CarbonImmutable::parse('2026-06-10'), 29);

    expect($start->toDateString())->toBe('2026-05-29')
        ->and($end->toDateString())->toBe('2026-06-28');
});

test('bimonthly budget cycles stay contiguous across end-of-month anchors', function () {
    $budget = new Budget([
        'frequency' => 'bimonthly',
        'anchor_date' => '2026-12-31',
        'ends_at' => null,
    ]);

    // Second cycle (index 1): 2027-02-28 .. 2027-04-29. The previous behaviour ended
    // this cycle on 2027-04-27, leaving Apr 28-29 uncovered before the next cycle
    // started on Apr 30.
    [$start, $end] = $budget->resolveCycleRange(CarbonImmutable::parse('2027-03-15'));

    expect($start->toDateString())->toBe('2027-02-28')
        ->and($end->toDateString())->toBe('2027-04-29');

    // The next cycle must start exactly one day after this one ends — no gap.
    [$nextStart] = $budget->resolveCycleRange(CarbonImmutable::parse('2027-05-15'));

    expect($end->addDay()->toDateString())->toBe($nextStart->toDateString());
});
