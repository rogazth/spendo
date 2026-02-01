<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Expense categories with their children.
     *
     * @var array<int, array{name: string, icon: string, color: string, children?: array<int, array{name: string, icon: string, color: string}>}>
     */
    private array $expenseCategories = [
        [
            'name' => 'Servicios',
            'icon' => 'home',
            'color' => '#F59E0B',
            'children' => [
                ['name' => 'Agua', 'icon' => 'droplet', 'color' => '#3B82F6'],
                ['name' => 'Electricidad', 'icon' => 'zap', 'color' => '#FBBF24'],
                ['name' => 'Gas', 'icon' => 'flame', 'color' => '#F97316'],
                ['name' => 'Internet', 'icon' => 'wifi', 'color' => '#8B5CF6'],
                ['name' => 'Teléfono', 'icon' => 'phone', 'color' => '#10B981'],
            ],
        ],
        [
            'name' => 'Vivienda',
            'icon' => 'building',
            'color' => '#6366F1',
            'children' => [
                ['name' => 'Arriendo', 'icon' => 'key', 'color' => '#6366F1'],
                ['name' => 'Dividendo', 'icon' => 'building-2', 'color' => '#8B5CF6'],
                ['name' => 'Mantención', 'icon' => 'tool', 'color' => '#F59E0B'],
                ['name' => 'Seguros', 'icon' => 'shield', 'color' => '#10B981'],
            ],
        ],
        [
            'name' => 'Alimentación',
            'icon' => 'shopping-cart',
            'color' => '#EF4444',
            'children' => [
                ['name' => 'Supermercado', 'icon' => 'shopping-cart', 'color' => '#EF4444'],
                ['name' => 'Restaurantes', 'icon' => 'utensils', 'color' => '#F97316'],
                ['name' => 'Delivery', 'icon' => 'bike', 'color' => '#EC4899'],
                ['name' => 'Café', 'icon' => 'coffee', 'color' => '#78350F'],
            ],
        ],
        [
            'name' => 'Transporte',
            'icon' => 'car',
            'color' => '#3B82F6',
            'children' => [
                ['name' => 'Combustible', 'icon' => 'fuel', 'color' => '#FBBF24'],
                ['name' => 'Transporte Público', 'icon' => 'train', 'color' => '#F97316'],
                ['name' => 'Uber/Taxi', 'icon' => 'car', 'color' => '#000000'],
                ['name' => 'Estacionamiento', 'icon' => 'parking', 'color' => '#6B7280'],
                ['name' => 'Peajes', 'icon' => 'route', 'color' => '#3B82F6'],
            ],
        ],
        [
            'name' => 'Entretenimiento',
            'icon' => 'film',
            'color' => '#8B5CF6',
            'children' => [
                ['name' => 'Streaming', 'icon' => 'tv', 'color' => '#E11D48'],
                ['name' => 'Juegos', 'icon' => 'gamepad', 'color' => '#8B5CF6'],
                ['name' => 'Salidas', 'icon' => 'glass', 'color' => '#EC4899'],
                ['name' => 'Hobbies', 'icon' => 'palette', 'color' => '#06B6D4'],
            ],
        ],
        [
            'name' => 'Salud',
            'icon' => 'heart',
            'color' => '#10B981',
            'children' => [
                ['name' => 'Médico', 'icon' => 'stethoscope', 'color' => '#3B82F6'],
                ['name' => 'Farmacia', 'icon' => 'pill', 'color' => '#10B981'],
                ['name' => 'Seguro de Salud', 'icon' => 'shield', 'color' => '#6366F1'],
            ],
        ],
        [
            'name' => 'Educación',
            'icon' => 'book',
            'color' => '#06B6D4',
            'children' => [
                ['name' => 'Cursos', 'icon' => 'certificate', 'color' => '#06B6D4'],
                ['name' => 'Libros', 'icon' => 'book', 'color' => '#84CC16'],
                ['name' => 'Suscripciones', 'icon' => 'credit-card', 'color' => '#8B5CF6'],
            ],
        ],
        [
            'name' => 'Compras',
            'icon' => 'shopping-bag',
            'color' => '#EC4899',
            'children' => [
                ['name' => 'Ropa', 'icon' => 'shirt', 'color' => '#EC4899'],
                ['name' => 'Tecnología', 'icon' => 'laptop', 'color' => '#6366F1'],
                ['name' => 'Hogar', 'icon' => 'home', 'color' => '#84CC16'],
            ],
        ],
        [
            'name' => 'Otros Gastos',
            'icon' => 'tag',
            'color' => '#6B7280',
        ],
    ];

    /**
     * Income categories.
     *
     * @var array<int, array{name: string, icon: string, color: string, children?: array<int, array{name: string, icon: string, color: string}>}>
     */
    private array $incomeCategories = [
        [
            'name' => 'Sueldo',
            'icon' => 'briefcase',
            'color' => '#10B981',
        ],
        [
            'name' => 'Freelance',
            'icon' => 'laptop',
            'color' => '#3B82F6',
        ],
        [
            'name' => 'Inversiones',
            'icon' => 'chart-line',
            'color' => '#8B5CF6',
            'children' => [
                ['name' => 'Dividendos', 'icon' => 'coins', 'color' => '#FBBF24'],
                ['name' => 'Intereses', 'icon' => 'percent', 'color' => '#10B981'],
                ['name' => 'Ganancias', 'icon' => 'trending-up', 'color' => '#22C55E'],
            ],
        ],
        [
            'name' => 'Arriendo',
            'icon' => 'home',
            'color' => '#F59E0B',
        ],
        [
            'name' => 'Reembolsos',
            'icon' => 'receipt',
            'color' => '#06B6D4',
        ],
        [
            'name' => 'Otros Ingresos',
            'icon' => 'tag',
            'color' => '#6B7280',
        ],
    ];

    /**
     * System categories (protected).
     *
     * @var array<int, array{name: string, icon: string, color: string}>
     */
    private array $systemCategories = [
        ['name' => 'Balance Inicial', 'icon' => 'wallet', 'color' => '#6B7280'],
        ['name' => 'Ajuste de Balance', 'icon' => 'refresh', 'color' => '#6B7280'],
        ['name' => 'Transferencia', 'icon' => 'arrows-right-left', 'color' => '#6B7280'],
        ['name' => 'Liquidación TDC', 'icon' => 'credit-card', 'color' => '#6B7280'],
    ];

    public function run(): void
    {
        // Create expense categories
        $this->createCategories($this->expenseCategories, CategoryType::Expense);

        // Create income categories
        $this->createCategories($this->incomeCategories, CategoryType::Income);

        // Create system categories (protected)
        foreach ($this->systemCategories as $categoryData) {
            Category::create([
                ...$categoryData,
                'user_id' => null,
                'parent_id' => null,
                'type' => CategoryType::System,
                'is_system' => true,
            ]);
        }
    }

    /**
     * @param  array<int, array{name: string, icon: string, color: string, children?: array<int, array{name: string, icon: string, color: string}>}>  $categories
     */
    private function createCategories(array $categories, CategoryType $type): void
    {
        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $parent = Category::create([
                ...$categoryData,
                'user_id' => null,
                'parent_id' => null,
                'type' => $type,
                'is_system' => false,
            ]);

            foreach ($children as $childData) {
                Category::create([
                    ...$childData,
                    'user_id' => null,
                    'parent_id' => $parent->id,
                    'type' => $type,
                    'is_system' => false,
                ]);
            }
        }
    }
}
