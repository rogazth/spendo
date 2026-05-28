<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SeedDefaultCategoriesAction
{
    /**
     * Minimal default category set seeded on user registration.
     * The user can rename, delete, or expand this tree freely.
     *
     * @var array<int, array{name: string, emoji: string, color: string}>
     */
    private array $defaults = [
        ['name' => 'Sueldo', 'emoji' => '💼', 'color' => '#10B981'],
        ['name' => 'Otros Ingresos', 'emoji' => '🏷️', 'color' => '#22C55E'],
        ['name' => 'Otros Gastos', 'emoji' => '🏷️', 'color' => '#6B7280'],
    ];

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            foreach ($this->defaults as $data) {
                Category::firstOrCreate(
                    ['user_id' => $user->id, 'name' => $data['name'], 'parent_id' => null],
                    [
                        'emoji' => $data['emoji'],
                        'color' => $data['color'],
                        'sort_order' => 0,
                    ],
                );
            }
        });
    }
}
