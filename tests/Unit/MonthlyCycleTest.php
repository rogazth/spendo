<?php

use App\Models\User;
use Carbon\CarbonImmutable;

dataset('monthly cycles', [
    'mid-month day, reference after start' => ['2026-06-20', 15, '2026-06-15', '2026-07-14'],
    'mid-month day, reference before start' => ['2026-06-10', 15, '2026-05-15', '2026-06-14'],
    'day 29 in non-leap february clamps to last day' => ['2026-02-28', 29, '2026-02-28', '2026-03-28'],
    'day 29 previous cycle ends on feb 27' => ['2026-02-10', 29, '2026-01-29', '2026-02-27'],
    'day 31 in april clamps to last day' => ['2026-04-15', 31, '2026-03-31', '2026-04-29'],
    'day 31 january into february' => ['2026-01-31', 31, '2026-01-31', '2026-02-27'],
    'day 29 in leap february stays on 29' => ['2024-02-29', 29, '2024-02-29', '2024-03-28'],
    'day 1 matches calendar month' => ['2026-06-15', 1, '2026-06-01', '2026-06-30'],
]);

test('resolveMonthlyCycleForDay clamps days that do not exist in a month', function (
    string $reference,
    int $day,
    string $expectedStart,
    string $expectedEnd,
) {
    [$start, $end] = User::resolveMonthlyCycleForDay(CarbonImmutable::parse($reference), $day);

    expect($start->toDateString())->toBe($expectedStart)
        ->and($end->toDateString())->toBe($expectedEnd);
})->with('monthly cycles');

test('monthly cycles are contiguous across a short month', function () {
    $day = 31;

    [, $end] = User::resolveMonthlyCycleForDay(CarbonImmutable::parse('2026-02-10'), $day);
    [$nextStart] = User::resolveMonthlyCycleForDay(CarbonImmutable::parse('2026-02-28'), $day);

    expect($end->addDay()->toDateString())->toBe($nextStart->toDateString());
});
