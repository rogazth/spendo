<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Services\InsightsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InsightsController extends Controller
{
    public function __construct(private readonly InsightsService $insights) {}

    public function index(): Response
    {
        $user = Auth::user();
        $now = CarbonImmutable::now()->startOfDay();

        $cashFlow = $this->insights->monthlyCashFlow($user, $now);
        $compliance = $this->insights->complianceGrid($user, $now);
        $trends = $this->insights->categoryTrends($user, $now);
        $capSuggestions = $this->insights->capSuggestions($user, $now);

        $currencies = collect(array_keys($cashFlow))
            ->merge(array_keys($compliance))
            ->merge(array_keys($trends))
            ->unique()
            ->sort()
            ->values();

        return Inertia::render('insights', [
            'sections' => $currencies->map(fn (string $currency) => [
                'currency' => $currency,
                'currency_locale' => Currency::localeFor($currency),
                'cash_flow' => $cashFlow[$currency] ?? [],
                'budgets' => $compliance[$currency] ?? [],
                'trends' => $trends[$currency] ?? [],
                'cap_suggestions' => $capSuggestions[$currency] ?? [],
            ])->all(),
        ]);
    }
}
