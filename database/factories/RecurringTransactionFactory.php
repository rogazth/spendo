<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'category_id' => Category::factory(),
            'amount' => fake()->numberBetween(500000, 10000000),
            'currency' => 'CLP',
            'description' => fake()->randomElement(['Netflix', 'Spotify', 'Arriendo', 'Internet', 'Teléfono', 'Luz', 'Agua', 'Gas']),
            'frequency' => 'monthly',
            'day_of_month' => fake()->numberBetween(1, 28),
            'day_of_week' => null,
            'start_date' => now()->startOfMonth(),
            'end_date' => null,
            'next_due_date' => now()->setDay(fake()->numberBetween(1, 28)),
            'auto_create' => false,
            'is_active' => true,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => 'monthly',
            'day_of_month' => fake()->numberBetween(1, 28),
            'day_of_week' => null,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => 'weekly',
            'day_of_month' => null,
            'day_of_week' => fake()->numberBetween(0, 6),
        ]);
    }

    public function autoCreate(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_create' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
