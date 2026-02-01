<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Budget>
 */
class BudgetFactory extends Factory
{
    public function definition(): array
    {
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        return [
            'user_id' => User::factory(),
            'name' => now()->format('F Y'),
            'currency' => 'CLP',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forMonth(int $month, int $year): static
    {
        $date = now()->setYear($year)->setMonth($month);

        return $this->state(fn (array $attributes) => [
            'name' => $date->format('F Y'),
            'period_start' => $date->startOfMonth()->toDateString(),
            'period_end' => $date->endOfMonth()->toDateString(),
        ]);
    }
}
