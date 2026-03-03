<?php

namespace Database\Factories;

use App\Enums\InstrumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Instrument>
 */
class InstrumentFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement([InstrumentType::Checking, InstrumentType::Savings, InstrumentType::CreditCard]);

        return [
            'user_id' => User::factory(),
            'name' => $this->getNameForType($type),
            'type' => $type,
            'currency' => 'CLP',
            'credit_limit' => $type === InstrumentType::CreditCard ? fake()->numberBetween(50000000, 500000000) : null,
            'billing_cycle_day' => $type === InstrumentType::CreditCard ? fake()->numberBetween(1, 28) : null,
            'payment_due_day' => $type === InstrumentType::CreditCard ? fake()->numberBetween(1, 28) : null,
            'color' => fake()->hexColor(),
            'icon' => null,
            'last_four_digits' => in_array($type, [InstrumentType::CreditCard, InstrumentType::PrepaidCard]) ? fake()->numerify('####') : null,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }

    public function creditCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InstrumentType::CreditCard,
            'name' => fake()->randomElement(['Visa', 'Mastercard']).' '.fake()->randomElement(['Santander', 'BCI', 'Banco de Chile', 'Falabella']),
            'credit_limit' => fake()->numberBetween(100000000, 500000000),
            'billing_cycle_day' => fake()->numberBetween(1, 28),
            'payment_due_day' => fake()->numberBetween(1, 28),
            'last_four_digits' => fake()->numerify('####'),
        ]);
    }

    public function checking(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InstrumentType::Checking,
            'name' => 'Cuenta Corriente '.fake()->randomElement(['Santander', 'BCI', 'Banco de Chile']),
            'credit_limit' => null,
            'billing_cycle_day' => null,
            'payment_due_day' => null,
            'last_four_digits' => null,
        ]);
    }

    public function savings(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InstrumentType::Savings,
            'name' => fake()->randomElement(['Mercado Pago', 'Tenpo', 'Cuenta Ahorro BCI']),
            'credit_limit' => null,
            'billing_cycle_day' => null,
            'payment_due_day' => null,
            'last_four_digits' => null,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InstrumentType::Cash,
            'name' => 'Efectivo',
            'credit_limit' => null,
            'billing_cycle_day' => null,
            'payment_due_day' => null,
            'last_four_digits' => null,
        ]);
    }

    public function prepaidCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InstrumentType::PrepaidCard,
            'name' => fake()->randomElement(['MACH', 'Cuenta RUT']),
            'credit_limit' => null,
            'billing_cycle_day' => null,
            'payment_due_day' => null,
            'last_four_digits' => fake()->numerify('####'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    private function getNameForType(InstrumentType $type): string
    {
        return match ($type) {
            InstrumentType::Checking => 'Cuenta Corriente '.fake()->randomElement(['Santander', 'BCI', 'Banco de Chile']),
            InstrumentType::Savings => fake()->randomElement(['Mercado Pago', 'Tenpo', 'Cuenta Ahorro']),
            InstrumentType::Cash => 'Efectivo',
            InstrumentType::Investment => 'Inversión '.fake()->randomElement(['DAP', 'Fondo Mutuo']),
            InstrumentType::CreditCard => fake()->randomElement(['Visa', 'Mastercard']).' '.fake()->randomElement(['Santander', 'BCI']),
            InstrumentType::PrepaidCard => fake()->randomElement(['MACH', 'Cuenta RUT']),
        };
    }
}
