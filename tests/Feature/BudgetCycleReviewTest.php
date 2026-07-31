<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetCycle;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSettings;
use App\Services\BudgetCycleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Currency::updateOrCreate(['code' => 'CLP'], ['name' => 'Peso chileno', 'locale' => 'es-CL']);
    Carbon::setTestNow('2026-07-31');
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeBudgetWithCap(int $cap = 300000): array
{
    $user = User::factory()->create();
    UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 1]);
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create(['name' => 'Comida']);

    $budget = Budget::factory()->for($user)->create([
        'account_id' => $account->id,
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-01-01',
        'is_active' => true,
    ]);

    BudgetItem::factory()->for($budget)->for($category)->create(['amount' => $cap]);

    return [$user, $account, $category, $budget];
}

test('closes the previous cycle with the caps in force at close time', function () {
    [$user, , , $budget] = makeBudgetWithCap(300000);

    $created = app(BudgetCycleService::class)
        ->materializeClosedCycles($user, CarbonImmutable::parse('2026-07-31'));

    expect($created)->toBe(1);

    $cycle = BudgetCycle::query()->where('budget_id', $budget->id)->with('items')->firstOrFail();
    expect($cycle->starts_at->toDateString())->toBe('2026-06-01')
        ->and($cycle->ends_at->toDateString())->toBe('2026-06-30')
        ->and($cycle->is_backfilled)->toBeFalse()
        ->and((float) $cycle->items->first()->cap_amount)->toBe(300000.0);
});

test('a raised cap does not rewrite an already closed cycle', function () {
    [$user, , , $budget] = makeBudgetWithCap(300000);

    app(BudgetCycleService::class)->materializeClosedCycles($user, CarbonImmutable::parse('2026-07-31'));

    $budget->items()->first()->update(['amount' => 500000]);
    app(BudgetCycleService::class)->materializeClosedCycles($user, CarbonImmutable::parse('2026-07-31'));

    $cycle = BudgetCycle::query()->where('budget_id', $budget->id)->with('items')->firstOrFail();
    expect(BudgetCycle::query()->count())->toBe(1)
        ->and((float) $cycle->items->first()->cap_amount)->toBe(300000.0);
});

test('a transaction backdated into a closed cycle changes its result', function () {
    [$user, $account, $category, $budget] = makeBudgetWithCap(300000);

    $cycles = app(BudgetCycleService::class);
    $cycles->materializeClosedCycles($user, CarbonImmutable::parse('2026-07-31'));
    $cycle = BudgetCycle::query()->where('budget_id', $budget->id)->with('items')->firstOrFail();

    expect($cycles->review($cycle)['spent'])->toBe(0.0);

    Transaction::factory()->for($user)->for($account)->for($category)->create([
        'amount' => -120000,
        'currency' => 'CLP',
        'transaction_date' => '2026-06-27',
        'exclude_from_budget' => false,
    ]);

    $review = $cycles->review($cycle->fresh('items'));
    expect($review['spent'])->toBe(120000.0)
        ->and($review['leftover'])->toBe(180000.0)
        ->and($review['over_items'])->toBe(0);
});

test('overspend is reported per item', function () {
    [$user, $account, $category, $budget] = makeBudgetWithCap(100000);

    Transaction::factory()->for($user)->for($account)->for($category)->create([
        'amount' => -160000,
        'currency' => 'CLP',
        'transaction_date' => '2026-06-10',
        'exclude_from_budget' => false,
    ]);

    $cycles = app(BudgetCycleService::class);
    $cycles->materializeClosedCycles($user, CarbonImmutable::parse('2026-07-31'));
    $cycle = BudgetCycle::query()->where('budget_id', $budget->id)->with('items')->firstOrFail();

    $review = $cycles->review($cycle);
    expect($review['overspend'])->toBe(60000.0)
        ->and($review['over_items'])->toBe(1)
        ->and($review['items'][0]['delta'])->toBe(-60000.0);
});

test('the budget page renders a post-mortem for a closed cycle', function () {
    [$user, $account, $category, $budget] = makeBudgetWithCap(300000);

    Transaction::factory()->for($user)->for($account)->for($category)->create([
        'amount' => -50000,
        'currency' => 'CLP',
        'transaction_date' => '2026-06-05',
        'exclude_from_budget' => false,
    ]);

    $this->actingAs($user)
        ->get("/budgets/{$budget->uuid}?cycle=2026-06-01")
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/show')
            ->where('cycleReview.start', '2026-06-01')
            ->where('cycleReview.spent', 50000)
            ->where('cycleReview.is_backfilled', true)
            ->has('cycleReview.burn', 30)
            ->has('cycleOptions')
        );
});

test('an open cycle has no post-mortem', function () {
    [$user, , , $budget] = makeBudgetWithCap();

    $this->actingAs($user)
        ->get("/budgets/{$budget->uuid}?cycle=2026-07-01")
        ->assertInertia(fn (Assert $page) => $page->where('cycleReview', null));
});
