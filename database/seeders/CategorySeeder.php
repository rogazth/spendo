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
     * @var array<int, array{name: string, emoji: string, color: string, children?: array<int, array{name: string, emoji: string, color: string}>}>
     */
    private array $expenseCategories = [
        [
            'name' => 'Servicios',
            'emoji' => '🏠',
            'color' => '#F59E0B',
            'children' => [
                ['name' => 'Agua', 'emoji' => '💧', 'color' => '#3B82F6'],
                ['name' => 'Electricidad', 'emoji' => '⚡', 'color' => '#FBBF24'],
                ['name' => 'Gas', 'emoji' => '🔥', 'color' => '#F97316'],
                ['name' => 'Internet', 'emoji' => '📡', 'color' => '#8B5CF6'],
                ['name' => 'Teléfono', 'emoji' => '📱', 'color' => '#10B981'],
                ['name' => 'Televisión', 'emoji' => '📺', 'color' => '#6366F1'],
                ['name' => 'Impuestos', 'emoji' => '🧾', 'color' => '#EF4444'],
            ],
        ],
        [
            'name' => 'Vivienda',
            'emoji' => '🏢',
            'color' => '#6366F1',
            'children' => [
                ['name' => 'Arriendo', 'emoji' => '🔑', 'color' => '#6366F1'],
                ['name' => 'Dividendo', 'emoji' => '🏗️', 'color' => '#8B5CF6'],
                ['name' => 'Mantención', 'emoji' => '🔧', 'color' => '#F59E0B'],
                ['name' => 'Seguros', 'emoji' => '🛡️', 'color' => '#10B981'],
                ['name' => 'Gasto Común', 'emoji' => '🏘️', 'color' => '#8B5CF6'],
                ['name' => 'Suministros del Hogar', 'emoji' => '📦', 'color' => '#84CC16'],
                ['name' => 'Préstamo Hipotecario', 'emoji' => '🪙', 'color' => '#F59E0B'],
                ['name' => 'Banco', 'emoji' => '🏛️', 'color' => '#6B7280'],
                ['name' => 'Cuentas', 'emoji' => '📄', 'color' => '#F59E0B'],
                ['name' => 'Servicio', 'emoji' => '⚙️', 'color' => '#6B7280'],
            ],
        ],
        [
            'name' => 'Alimentación',
            'emoji' => '🛒',
            'color' => '#EF4444',
            'children' => [
                ['name' => 'Supermercado', 'emoji' => '🛒', 'color' => '#EF4444'],
                ['name' => 'Restaurantes', 'emoji' => '🍽️', 'color' => '#F97316'],
                ['name' => 'Delivery', 'emoji' => '🚴', 'color' => '#EC4899'],
                ['name' => 'Café', 'emoji' => '☕', 'color' => '#78350F'],
                ['name' => 'Comida', 'emoji' => '🍳', 'color' => '#F97316'],
                ['name' => 'Golosinas', 'emoji' => '🍬', 'color' => '#EC4899'],
                ['name' => 'Bebidas', 'emoji' => '🍷', 'color' => '#7C3AED'],
            ],
        ],
        [
            'name' => 'Transporte',
            'emoji' => '🚗',
            'color' => '#3B82F6',
            'children' => [
                ['name' => 'Combustible', 'emoji' => '⛽', 'color' => '#FBBF24'],
                ['name' => 'Transporte Público', 'emoji' => '🚆', 'color' => '#F97316'],
                ['name' => 'Uber/Taxi', 'emoji' => '🚕', 'color' => '#000000'],
                ['name' => 'Estacionamiento', 'emoji' => '🅿️', 'color' => '#6B7280'],
                ['name' => 'Peajes', 'emoji' => '🛣️', 'color' => '#3B82F6'],
                ['name' => 'Seguro de Auto', 'emoji' => '🛡️', 'color' => '#10B981'],
                ['name' => 'Préstamo Auto', 'emoji' => '🚗', 'color' => '#F59E0B'],
                ['name' => 'Vuelos', 'emoji' => '✈️', 'color' => '#6366F1'],
                ['name' => 'Costos del Auto', 'emoji' => '🔧', 'color' => '#6B7280'],
                ['name' => 'Reparación', 'emoji' => '🔩', 'color' => '#F97316'],
            ],
        ],
        [
            'name' => 'Entretenimiento',
            'emoji' => '🎬',
            'color' => '#8B5CF6',
            'children' => [
                ['name' => 'Streaming', 'emoji' => '📺', 'color' => '#E11D48'],
                ['name' => 'Juegos', 'emoji' => '🎮', 'color' => '#8B5CF6'],
                ['name' => 'Salidas', 'emoji' => '🥂', 'color' => '#EC4899'],
                ['name' => 'Hobbies', 'emoji' => '🎨', 'color' => '#06B6D4'],
                ['name' => 'Cine', 'emoji' => '🎥', 'color' => '#E11D48'],
                ['name' => 'Conciertos', 'emoji' => '🎤', 'color' => '#EC4899'],
                ['name' => 'Deportes', 'emoji' => '🏆', 'color' => '#3B82F6'],
                ['name' => 'Gimnasio', 'emoji' => '💪', 'color' => '#EC4899'],
                ['name' => 'Discoteca', 'emoji' => '🪩', 'color' => '#7C3AED'],
                ['name' => 'Boliche', 'emoji' => '🎳', 'color' => '#EC4899'],
                ['name' => 'Suscripción', 'emoji' => '▶️', 'color' => '#E11D48'],
                ['name' => 'Vacaciones', 'emoji' => '☀️', 'color' => '#10B981'],
            ],
        ],
        [
            'name' => 'Salud',
            'emoji' => '❤️',
            'color' => '#10B981',
            'children' => [
                ['name' => 'Médico', 'emoji' => '🩺', 'color' => '#3B82F6'],
                ['name' => 'Farmacia', 'emoji' => '💊', 'color' => '#10B981'],
                ['name' => 'Seguro de Salud', 'emoji' => '🛡️', 'color' => '#6366F1'],
                ['name' => 'Dentista', 'emoji' => '🦷', 'color' => '#EC4899'],
                ['name' => 'Óptica', 'emoji' => '👓', 'color' => '#06B6D4'],
                ['name' => 'Psicólogo', 'emoji' => '🧠', 'color' => '#8B5CF6'],
            ],
        ],
        [
            'name' => 'Educación',
            'emoji' => '📚',
            'color' => '#06B6D4',
            'children' => [
                ['name' => 'Cursos', 'emoji' => '📜', 'color' => '#06B6D4'],
                ['name' => 'Libros', 'emoji' => '📗', 'color' => '#84CC16'],
                ['name' => 'Suscripciones', 'emoji' => '💳', 'color' => '#8B5CF6'],
            ],
        ],
        [
            'name' => 'Compras',
            'emoji' => '🛍️',
            'color' => '#EC4899',
            'children' => [
                ['name' => 'Ropa', 'emoji' => '👕', 'color' => '#EC4899'],
                ['name' => 'Tecnología', 'emoji' => '💻', 'color' => '#6366F1'],
                ['name' => 'Hogar', 'emoji' => '🪑', 'color' => '#84CC16'],
                ['name' => 'Electrónica', 'emoji' => '🖥️', 'color' => '#6366F1'],
                ['name' => 'Accesorios', 'emoji' => '⌚', 'color' => '#F97316'],
                ['name' => 'Deportes y Outdoors', 'emoji' => '🚴', 'color' => '#3B82F6'],
            ],
        ],
        [
            'name' => 'Mascotas',
            'emoji' => '🐾',
            'color' => '#F97316',
            'children' => [
                ['name' => 'Alimento Mascota', 'emoji' => '🦴', 'color' => '#F97316'],
                ['name' => 'Veterinario', 'emoji' => '🩺', 'color' => '#10B981'],
                ['name' => 'Accesorios Mascota', 'emoji' => '🐶', 'color' => '#F59E0B'],
                ['name' => 'Peluquería Mascota', 'emoji' => '✂️', 'color' => '#EC4899'],
            ],
        ],
        [
            'name' => 'Viajes',
            'emoji' => '🗺️',
            'color' => '#0EA5E9',
            'children' => [
                ['name' => 'Hotel', 'emoji' => '🛏️', 'color' => '#0EA5E9'],
                ['name' => 'Tour / Actividades', 'emoji' => '🎒', 'color' => '#10B981'],
                ['name' => 'Seguro de Viaje', 'emoji' => '🛡️', 'color' => '#6366F1'],
                ['name' => 'Equipaje', 'emoji' => '🧳', 'color' => '#F59E0B'],
            ],
        ],
        [
            'name' => 'Estilo de Vida',
            'emoji' => '✨',
            'color' => '#EC4899',
            'children' => [
                ['name' => 'Donaciones', 'emoji' => '🤝', 'color' => '#EC4899'],
                ['name' => 'Cuidado Infantil', 'emoji' => '👶', 'color' => '#FB923C'],
                ['name' => 'Regalos', 'emoji' => '🎁', 'color' => '#EC4899'],
                ['name' => 'Trabajo / Oficina', 'emoji' => '💼', 'color' => '#6366F1'],
                ['name' => 'Comunidad', 'emoji' => '👥', 'color' => '#84CC16'],
            ],
        ],
        [
            'name' => 'Finanzas',
            'emoji' => '🏦',
            'color' => '#6B7280',
            'children' => [
                ['name' => 'Comisiones Bancarias', 'emoji' => '🏦', 'color' => '#6B7280'],
                ['name' => 'Préstamo Estudiantil', 'emoji' => '🎓', 'color' => '#6366F1'],
                ['name' => 'Intereses Deuda', 'emoji' => '📉', 'color' => '#EF4444'],
                ['name' => 'Multas', 'emoji' => '⚠️', 'color' => '#F97316'],
            ],
        ],
        [
            'name' => 'Otros Gastos',
            'emoji' => '🏷️',
            'color' => '#6B7280',
            'children' => [
                ['name' => 'Desconocido', 'emoji' => '❓', 'color' => '#6B7280'],
            ],
        ],
    ];

    /**
     * Income categories.
     *
     * @var array<int, array{name: string, emoji: string, color: string, children?: array<int, array{name: string, emoji: string, color: string}>}>
     */
    private array $incomeCategories = [
        [
            'name' => 'Ingresos',
            'emoji' => '💵',
            'color' => '#22C55E',
        ],
        [
            'name' => 'Sueldo',
            'emoji' => '💼',
            'color' => '#10B981',
        ],
        [
            'name' => 'Freelance',
            'emoji' => '💻',
            'color' => '#3B82F6',
        ],
        [
            'name' => 'Inversiones',
            'emoji' => '📈',
            'color' => '#8B5CF6',
            'children' => [
                ['name' => 'Dividendos', 'emoji' => '🪙', 'color' => '#FBBF24'],
                ['name' => 'Intereses', 'emoji' => '💱', 'color' => '#10B981'],
                ['name' => 'Ganancias', 'emoji' => '💹', 'color' => '#22C55E'],
            ],
        ],
        [
            'name' => 'Arriendo',
            'emoji' => '🏠',
            'color' => '#F59E0B',
        ],
        [
            'name' => 'Reembolsos',
            'emoji' => '🧾',
            'color' => '#06B6D4',
        ],
        [
            'name' => 'Pensión',
            'emoji' => '🐷',
            'color' => '#10B981',
        ],
        [
            'name' => 'Beneficio Familiar',
            'emoji' => '👨‍👩‍👧‍👦',
            'color' => '#06B6D4',
        ],
        [
            'name' => 'Bono / Aguinaldo',
            'emoji' => '🎁',
            'color' => '#EC4899',
        ],
        [
            'name' => 'Venta de Activos',
            'emoji' => '📈',
            'color' => '#22C55E',
        ],
        [
            'name' => 'Ahorros',
            'emoji' => '🐷',
            'color' => '#06B6D4',
            'children' => [
                ['name' => 'Ahorro de Emergencia', 'emoji' => '⭐', 'color' => '#06B6D4'],
                ['name' => 'Ahorro General', 'emoji' => '🐷', 'color' => '#06B6D4'],
                ['name' => 'Ahorro para Vacaciones', 'emoji' => '🌂', 'color' => '#06B6D4'],
            ],
        ],
        [
            'name' => 'Otros Ingresos',
            'emoji' => '🏷️',
            'color' => '#6B7280',
        ],
    ];

    /**
     * System categories (protected).
     *
     * @var array<int, array{name: string, emoji: string, color: string}>
     */
    private array $systemCategories = [
        ['name' => 'Balance Inicial', 'emoji' => '💰', 'color' => '#6B7280'],
        ['name' => 'Ajuste de Balance', 'emoji' => '🔄', 'color' => '#6B7280'],
        ['name' => 'Transferencia', 'emoji' => '↔️', 'color' => '#6B7280'],
        ['name' => 'Liquidación TDC', 'emoji' => '💳', 'color' => '#6B7280'],
    ];

    public function run(): void
    {
        $this->createCategories($this->expenseCategories, CategoryType::Expense);
        $this->createCategories($this->incomeCategories, CategoryType::Income);

        foreach ($this->systemCategories as $categoryData) {
            Category::updateOrCreate(
                ['name' => $categoryData['name'], 'user_id' => null, 'is_system' => true],
                [
                    ...$categoryData,
                    'user_id' => null,
                    'parent_id' => null,
                    'type' => CategoryType::System,
                    'is_system' => true,
                ]
            );
        }
    }

    /**
     * @param  array<int, array{name: string, emoji: string, color: string, children?: array<int, array{name: string, emoji: string, color: string}>}>  $categories
     */
    private function createCategories(array $categories, CategoryType $type): void
    {
        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $parent = Category::updateOrCreate(
                ['name' => $categoryData['name'], 'user_id' => null, 'parent_id' => null],
                [
                    ...$categoryData,
                    'user_id' => null,
                    'parent_id' => null,
                    'type' => $type,
                    'is_system' => false,
                ]
            );

            foreach ($children as $childData) {
                Category::updateOrCreate(
                    ['name' => $childData['name'], 'user_id' => null, 'parent_id' => $parent->id],
                    [
                        ...$childData,
                        'user_id' => null,
                        'parent_id' => $parent->id,
                        'type' => $type,
                        'is_system' => false,
                    ]
                );
            }
        }
    }
}
