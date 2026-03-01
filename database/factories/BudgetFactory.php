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
        $anchorDate = now()->startOfMonth();

        return [
            'user_id' => User::factory(),
            'name' => now()->format('F Y'),
            'description' => fake()->optional()->sentence(),
            'currency' => 'CLP',
            'frequency' => 'monthly',
            'anchor_date' => $anchorDate,
            'ends_at' => null,
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
            'frequency' => 'monthly',
            'anchor_date' => $date->startOfMonth()->toDateString(),
            'ends_at' => $date->endOfMonth()->toDateString(),
        ]);
    }
}
