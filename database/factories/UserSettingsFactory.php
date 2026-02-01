<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserSettings>
 */
class UserSettingsFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'default_currency' => 'CLP',
            'budget_cycle_start_day' => 1,
            'timezone' => 'America/Santiago',
        ];
    }

    public function withCycleDay(int $day): static
    {
        return $this->state(fn (array $attributes) => [
            'budget_cycle_start_day' => $day,
        ]);
    }
}
