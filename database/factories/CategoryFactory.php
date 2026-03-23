<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /** @var array<int, array{name: string, emoji: string, color: string}> */
    private static array $expenseCategories = [
        ['name' => 'Alimentación', 'emoji' => '🛒', 'color' => '#EF4444'],
        ['name' => 'Transporte', 'emoji' => '🚗', 'color' => '#3B82F6'],
        ['name' => 'Entretenimiento', 'emoji' => '🎬', 'color' => '#8B5CF6'],
        ['name' => 'Salud', 'emoji' => '❤️', 'color' => '#10B981'],
        ['name' => 'Servicios', 'emoji' => '🏠', 'color' => '#F59E0B'],
        ['name' => 'Educación', 'emoji' => '📚', 'color' => '#06B6D4'],
        ['name' => 'Ropa', 'emoji' => '👕', 'color' => '#EC4899'],
        ['name' => 'Restaurantes', 'emoji' => '🍽️', 'color' => '#F97316'],
        ['name' => 'Tecnología', 'emoji' => '💻', 'color' => '#6366F1'],
        ['name' => 'Hogar', 'emoji' => '🪑', 'color' => '#84CC16'],
        ['name' => 'Mascotas', 'emoji' => '🐾', 'color' => '#A855F7'],
        ['name' => 'Otros Gastos', 'emoji' => '🏷️', 'color' => '#6B7280'],
    ];

    public function definition(): array
    {
        $category = fake()->randomElement(self::$expenseCategories);

        return [
            'user_id' => null,
            'parent_id' => null,
            'name' => $category['name'],
            'type' => CategoryType::Expense,
            'emoji' => $category['emoji'],
            'color' => $category['color'],
            'is_system' => false,
            'sort_order' => 0,
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CategoryType::Expense,
        ]);
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CategoryType::Income,
            'name' => fake()->randomElement(['Sueldo', 'Freelance', 'Inversiones', 'Arriendo', 'Reembolsos', 'Otros Ingresos']),
            'emoji' => '💼',
            'color' => '#10B981',
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'type' => CategoryType::System,
            'is_system' => true,
        ]);
    }

    public function userCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Mi categoría', 'Gastos personales', 'Regalos', 'Viajes', 'Suscripciones']),
            'is_system' => false,
        ]);
    }

    public function subcategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => Category::factory(),
        ]);
    }

    public function alimentacion(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Alimentación',
            'type' => CategoryType::Expense,
            'emoji' => '🛒',
            'color' => '#EF4444',
        ]);
    }

    public function transporte(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Transporte',
            'type' => CategoryType::Expense,
            'emoji' => '🚗',
            'color' => '#3B82F6',
        ]);
    }

    public function restaurantes(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Restaurantes',
            'type' => CategoryType::Expense,
            'emoji' => '🍽️',
            'color' => '#F97316',
        ]);
    }
}
