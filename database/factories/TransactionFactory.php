<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /** @var array<int, string> */
    private static array $merchants = [
        'Supermercado Líder',
        'Jumbo',
        'Santa Isabel',
        'Starbucks',
        'McDonald\'s',
        'Uber',
        'Uber Eats',
        'Rappi',
        'Copec',
        'Shell',
        'Farmacias Ahumada',
        'Farmacias Cruz Verde',
        'Falabella',
        'Ripley',
        'Paris',
        'Netflix',
        'Spotify',
        'Amazon',
        'MercadoLibre',
        'Cornershop',
        'PedidosYa',
        'VTR',
        'Movistar',
        'Entel',
        'Claro',
        'Enel',
        'Aguas Andinas',
        'Metrogas',
    ];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => TransactionType::Expense,
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'linked_transaction_id' => null,
            'amount' => fake()->numberBetween(100000, 15000000),
            'currency' => 'CLP',
            'description' => 'Compra en '.fake()->randomElement(self::$merchants),
            'notes' => fake()->optional(0.2)->sentence(),
            'exclude_from_budget' => false,
            'transaction_date' => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Expense,
            'amount' => fake()->numberBetween(100000, 15000000),
        ]);
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Income,
            'amount' => fake()->numberBetween(50000000, 300000000),
            'description' => fake()->randomElement(['Sueldo', 'Transferencia recibida', 'Pago freelance', 'Devolución']),
        ]);
    }

    public function transferOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::TransferOut,
            'amount' => fake()->numberBetween(1000000, 50000000),
            'description' => 'Transferencia saliente',
        ]);
    }

    public function transferIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::TransferIn,
            'amount' => fake()->numberBetween(1000000, 50000000),
            'description' => 'Transferencia entrante',
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_date' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function thisMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_date' => fake()->dateTimeBetween('first day of this month', 'now'),
        ]);
    }
}
