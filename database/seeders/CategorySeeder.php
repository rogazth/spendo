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
                ['name' => 'Televisión', 'icon' => 'monitor', 'color' => '#6366F1'],
                ['name' => 'Impuestos', 'icon' => 'receipt', 'color' => '#EF4444'],
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
                ['name' => 'Gasto Común', 'icon' => 'building-2', 'color' => '#8B5CF6'],
                ['name' => 'Suministros del Hogar', 'icon' => 'package', 'color' => '#84CC16'],
                ['name' => 'Préstamo Hipotecario', 'icon' => 'coins', 'color' => '#F59E0B'],
                ['name' => 'Banco', 'icon' => 'landmark', 'color' => '#6B7280'],
                ['name' => 'Cuentas', 'icon' => 'file-text', 'color' => '#F59E0B'],
                ['name' => 'Servicio', 'icon' => 'settings', 'color' => '#6B7280'],
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
                ['name' => 'Comida', 'icon' => 'utensils', 'color' => '#F97316'],
                ['name' => 'Golosinas', 'icon' => 'candy', 'color' => '#EC4899'],
                ['name' => 'Bebidas', 'icon' => 'wine', 'color' => '#7C3AED'],
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
                ['name' => 'Seguro de Auto', 'icon' => 'shield-check', 'color' => '#10B981'],
                ['name' => 'Préstamo Auto', 'icon' => 'car', 'color' => '#F59E0B'],
                ['name' => 'Vuelos', 'icon' => 'plane', 'color' => '#6366F1'],
                ['name' => 'Costos del Auto', 'icon' => 'car', 'color' => '#6B7280'],
                ['name' => 'Reparación', 'icon' => 'wrench', 'color' => '#F97316'],
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
                ['name' => 'Cine', 'icon' => 'clapperboard', 'color' => '#E11D48'],
                ['name' => 'Conciertos', 'icon' => 'mic', 'color' => '#EC4899'],
                ['name' => 'Deportes', 'icon' => 'trophy', 'color' => '#3B82F6'],
                ['name' => 'Gimnasio', 'icon' => 'dumbbell', 'color' => '#EC4899'],
                ['name' => 'Discoteca', 'icon' => 'beer', 'color' => '#7C3AED'],
                ['name' => 'Boliche', 'icon' => 'target', 'color' => '#EC4899'],
                ['name' => 'Suscripción', 'icon' => 'play-circle', 'color' => '#E11D48'],
                ['name' => 'Vacaciones', 'icon' => 'sun', 'color' => '#10B981'],
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
                ['name' => 'Dentista', 'icon' => 'smile', 'color' => '#EC4899'],
                ['name' => 'Óptica', 'icon' => 'glasses', 'color' => '#06B6D4'],
                ['name' => 'Psicólogo', 'icon' => 'brain', 'color' => '#8B5CF6'],
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
                ['name' => 'Electrónica', 'icon' => 'cpu', 'color' => '#6366F1'],
                ['name' => 'Accesorios', 'icon' => 'watch', 'color' => '#F97316'],
                ['name' => 'Deportes y Outdoors', 'icon' => 'bike', 'color' => '#3B82F6'],
            ],
        ],
        [
            'name' => 'Mascotas',
            'icon' => 'paw-print',
            'color' => '#F97316',
            'children' => [
                ['name' => 'Alimento Mascota', 'icon' => 'utensils', 'color' => '#F97316'],
                ['name' => 'Veterinario', 'icon' => 'stethoscope', 'color' => '#10B981'],
                ['name' => 'Accesorios Mascota', 'icon' => 'package', 'color' => '#F59E0B'],
                ['name' => 'Peluquería Mascota', 'icon' => 'scissors', 'color' => '#EC4899'],
            ],
        ],
        [
            'name' => 'Viajes',
            'icon' => 'map-pin',
            'color' => '#0EA5E9',
            'children' => [
                ['name' => 'Hotel', 'icon' => 'bed', 'color' => '#0EA5E9'],
                ['name' => 'Tour / Actividades', 'icon' => 'map', 'color' => '#10B981'],
                ['name' => 'Seguro de Viaje', 'icon' => 'shield', 'color' => '#6366F1'],
                ['name' => 'Equipaje', 'icon' => 'luggage', 'color' => '#F59E0B'],
            ],
        ],
        [
            'name' => 'Estilo de Vida',
            'icon' => 'sparkles',
            'color' => '#EC4899',
            'children' => [
                ['name' => 'Donaciones', 'icon' => 'heart-handshake', 'color' => '#EC4899'],
                ['name' => 'Cuidado Infantil', 'icon' => 'baby', 'color' => '#FB923C'],
                ['name' => 'Regalos', 'icon' => 'gift', 'color' => '#EC4899'],
                ['name' => 'Trabajo / Oficina', 'icon' => 'briefcase', 'color' => '#6366F1'],
                ['name' => 'Comunidad', 'icon' => 'users', 'color' => '#84CC16'],
            ],
        ],
        [
            'name' => 'Finanzas',
            'icon' => 'landmark',
            'color' => '#6B7280',
            'children' => [
                ['name' => 'Comisiones Bancarias', 'icon' => 'landmark', 'color' => '#6B7280'],
                ['name' => 'Préstamo Estudiantil', 'icon' => 'graduation-cap', 'color' => '#6366F1'],
                ['name' => 'Intereses Deuda', 'icon' => 'percent', 'color' => '#EF4444'],
                ['name' => 'Multas', 'icon' => 'alert-triangle', 'color' => '#F97316'],
            ],
        ],
        [
            'name' => 'Otros Gastos',
            'icon' => 'tag',
            'color' => '#6B7280',
            'children' => [
                ['name' => 'Desconocido', 'icon' => 'help-circle', 'color' => '#6B7280'],
            ],
        ],
    ];

    /**
     * Income categories.
     *
     * @var array<int, array{name: string, icon: string, color: string, children?: array<int, array{name: string, icon: string, color: string}>}>
     */
    private array $incomeCategories = [
        [
            'name' => 'Ingresos',
            'icon' => 'banknote',
            'color' => '#22C55E',
        ],
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
            'name' => 'Pensión',
            'icon' => 'piggy-bank',
            'color' => '#10B981',
        ],
        [
            'name' => 'Beneficio Familiar',
            'icon' => 'users',
            'color' => '#06B6D4',
        ],
        [
            'name' => 'Bono / Aguinaldo',
            'icon' => 'gift',
            'color' => '#EC4899',
        ],
        [
            'name' => 'Venta de Activos',
            'icon' => 'trending-up',
            'color' => '#22C55E',
        ],
        [
            'name' => 'Ahorros',
            'icon' => 'piggy-bank',
            'color' => '#06B6D4',
            'children' => [
                ['name' => 'Ahorro de Emergencia', 'icon' => 'star', 'color' => '#06B6D4'],
                ['name' => 'Ahorro General', 'icon' => 'piggy-bank', 'color' => '#06B6D4'],
                ['name' => 'Ahorro para Vacaciones', 'icon' => 'umbrella', 'color' => '#06B6D4'],
            ],
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
     * @param  array<int, array{name: string, icon: string, color: string, children?: array<int, array{name: string, icon: string, color: string}>}>  $categories
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
