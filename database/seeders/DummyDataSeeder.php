<?php

namespace Database\Seeders;

use App\Actions\Categories\SeedDefaultCategoriesAction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $seedCategories = app(SeedDefaultCategoriesAction::class);

        $mainUser = User::factory()->create([
            'name' => 'Gabriel Rodriguez',
            'email' => 'gabriel98rl@gmail.com',
            'password' => Hash::make('luis1998'),
        ]);
        $seedCategories->handle($mainUser);

        $moderateUser = User::factory()->create([
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
            'password' => Hash::make('password'),
        ]);
        $seedCategories->handle($moderateUser);
        $this->seedUserWithModerateData($moderateUser);

        $emptyUser = User::factory()->create([
            'name' => 'Maria Garcia',
            'email' => 'maria@example.com',
            'password' => Hash::make('password'),
        ]);
        $seedCategories->handle($emptyUser);
    }

    private function seedUserWithModerateData(User $user): void
    {
        $personalAccount = Account::factory()->default()->create([
            'user_id' => $user->id,
            'name' => 'Personal',
            'color' => '#3B82F6',
        ]);

        $casaAccount = Account::factory()->create([
            'user_id' => $user->id,
            'name' => 'Casa',
            'color' => '#10B981',
        ]);

        $sueldo = $user->categories()->where('name', 'Sueldo')->first();
        $otrosGastos = $user->categories()->where('name', 'Otros Gastos')->first();

        $expenseExtras = collect(['Alimentación', 'Transporte', 'Servicios', 'Entretenimiento'])
            ->map(fn (string $name) => Category::create([
                'user_id' => $user->id,
                'name' => $name,
                'emoji' => '🏷️',
                'color' => '#6B7280',
                'sort_order' => 0,
            ]));

        $expensePool = $expenseExtras->push($otrosGastos)->filter();

        for ($month = 2; $month >= 0; $month--) {
            Transaction::factory()->income()->create([
                'user_id' => $user->id,
                'account_id' => $personalAccount->id,
                'category_id' => $sueldo?->id,
                'amount' => fake()->numberBetween(1800000, 2200000),
                'description' => 'Sueldo',
                'transaction_date' => now()->subMonths($month)->startOfMonth()->addDays(5),
            ]);
        }

        for ($i = 0; $i < 25; $i++) {
            $category = $expensePool->random();

            Transaction::factory()->expense()->create([
                'user_id' => $user->id,
                'account_id' => $personalAccount->id,
                'category_id' => $category->id,
                'amount' => -fake()->numberBetween(5000, 100000),
                'transaction_date' => now()->subDays(fake()->numberBetween(0, 90)),
            ]);
        }

        for ($i = 0; $i < 10; $i++) {
            $category = $expensePool->random();

            Transaction::factory()->expense()->create([
                'user_id' => $user->id,
                'account_id' => $casaAccount->id,
                'category_id' => $category->id,
                'amount' => -fake()->numberBetween(5000, 100000),
                'transaction_date' => now()->subDays(fake()->numberBetween(0, 90)),
            ]);
        }
    }
}
