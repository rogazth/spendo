<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(AccountType::cases());

        return [
            'user_id' => User::factory(),
            'name' => $this->getNameForType($type),
            'type' => $type,
            'currency' => 'CLP',
            'initial_balance' => fake()->numberBetween(0, 500000000),
            'color' => fake()->hexColor(),
            'icon' => $type->icon(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function checking(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountType::Checking,
            'name' => 'Cuenta Corriente '.fake()->company(),
            'icon' => AccountType::Checking->icon(),
        ]);
    }

    public function savings(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountType::Savings,
            'name' => 'Cuenta de Ahorro '.fake()->company(),
            'icon' => AccountType::Savings->icon(),
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountType::Cash,
            'name' => 'Efectivo',
            'initial_balance' => fake()->numberBetween(0, 50000000),
            'icon' => AccountType::Cash->icon(),
        ]);
    }

    public function investment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountType::Investment,
            'name' => 'Inversión '.fake()->randomElement(['DAP', 'Fondo Mutuo', 'Acciones']),
            'icon' => AccountType::Investment->icon(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    private function getNameForType(AccountType $type): string
    {
        return match ($type) {
            AccountType::Checking => 'Cuenta Corriente '.fake()->randomElement(['Santander', 'BCI', 'Banco de Chile', 'Scotiabank']),
            AccountType::Savings => 'Cuenta de Ahorro '.fake()->randomElement(['Santander', 'BCI', 'Banco de Chile']),
            AccountType::Cash => 'Efectivo',
            AccountType::Investment => fake()->randomElement(['DAP Santander', 'Fondo Mutuo BCI', 'Acciones IPSA']),
        };
    }
}
