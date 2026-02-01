<?php

namespace Database\Factories;

use App\Enums\PaymentMethodType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(PaymentMethodType::cases());

        return [
            'user_id' => User::factory(),
            'name' => $this->getNameForType($type),
            'type' => $type,
            'linked_account_id' => null,
            'currency' => 'CLP',
            'credit_limit' => $type === PaymentMethodType::CreditCard ? fake()->numberBetween(50000000, 500000000) : null,
            'billing_cycle_day' => $type === PaymentMethodType::CreditCard ? fake()->numberBetween(1, 28) : null,
            'payment_due_day' => $type === PaymentMethodType::CreditCard ? fake()->numberBetween(1, 28) : null,
            'color' => fake()->hexColor(),
            'icon' => $type->icon(),
            'last_four_digits' => in_array($type, [PaymentMethodType::CreditCard, PaymentMethodType::DebitCard, PaymentMethodType::PrepaidCard]) ? fake()->numerify('####') : null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function creditCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PaymentMethodType::CreditCard,
            'name' => fake()->randomElement(['Visa', 'Mastercard', 'American Express']).' '.fake()->randomElement(['Santander', 'BCI', 'Banco de Chile', 'Falabella']),
            'credit_limit' => fake()->numberBetween(100000000, 500000000),
            'billing_cycle_day' => fake()->numberBetween(1, 28),
            'payment_due_day' => fake()->numberBetween(1, 28),
            'last_four_digits' => fake()->numerify('####'),
            'icon' => PaymentMethodType::CreditCard->icon(),
        ]);
    }

    public function debitCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PaymentMethodType::DebitCard,
            'name' => 'Débito '.fake()->randomElement(['Santander', 'BCI', 'Banco de Chile']),
            'linked_account_id' => Account::factory(),
            'credit_limit' => null,
            'billing_cycle_day' => null,
            'payment_due_day' => null,
            'last_four_digits' => fake()->numerify('####'),
            'icon' => PaymentMethodType::DebitCard->icon(),
        ]);
    }

    public function prepaidCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PaymentMethodType::PrepaidCard,
            'name' => 'Prepago '.fake()->randomElement(['MACH', 'Tenpo', 'Cuenta RUT']),
            'linked_account_id' => Account::factory(),
            'credit_limit' => null,
            'billing_cycle_day' => null,
            'payment_due_day' => null,
            'last_four_digits' => fake()->numerify('####'),
            'icon' => PaymentMethodType::PrepaidCard->icon(),
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PaymentMethodType::Cash,
            'name' => 'Efectivo',
            'linked_account_id' => Account::factory()->cash(),
            'credit_limit' => null,
            'billing_cycle_day' => null,
            'payment_due_day' => null,
            'last_four_digits' => null,
            'icon' => PaymentMethodType::Cash->icon(),
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PaymentMethodType::Transfer,
            'name' => 'Transferencia '.fake()->randomElement(['Santander', 'BCI', 'Banco de Chile']),
            'linked_account_id' => Account::factory()->checking(),
            'credit_limit' => null,
            'billing_cycle_day' => null,
            'payment_due_day' => null,
            'last_four_digits' => null,
            'icon' => PaymentMethodType::Transfer->icon(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    private function getNameForType(PaymentMethodType $type): string
    {
        return match ($type) {
            PaymentMethodType::CreditCard => fake()->randomElement(['Visa', 'Mastercard']).' '.fake()->randomElement(['Santander', 'BCI', 'Falabella']),
            PaymentMethodType::DebitCard => 'Débito '.fake()->randomElement(['Santander', 'BCI', 'Banco de Chile']),
            PaymentMethodType::PrepaidCard => 'Prepago '.fake()->randomElement(['MACH', 'Tenpo', 'Cuenta RUT']),
            PaymentMethodType::Cash => 'Efectivo',
            PaymentMethodType::Transfer => 'Transferencia',
        };
    }
}
