<?php

namespace Database\Seeders;

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
        $expenseCategories = Category::where('type', 'expense')->whereNull('parent_id')->get();
        $incomeCategories = Category::where('type', 'income')->whereNull('parent_id')->get();

        // User 1: Main user (no dummy data — real user)
        User::factory()->create([
            'name' => 'Gabriel Rodriguez',
            'email' => 'gabriel98rl@gmail.com',
            'password' => Hash::make('luis1998'),
        ]);

        // User 2: User with moderate dummy data
        $moderateUser = User::factory()->create([
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->seedUserWithModerateData($moderateUser, $expenseCategories, $incomeCategories);

        // User 3: Empty user (no data)
        User::factory()->create([
            'name' => 'Maria Garcia',
            'email' => 'maria@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function seedUserWithModerateData(User $user, $expenseCategories, $incomeCategories): void
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

        // Create income (last 3 months) to Personal account
        for ($month = 2; $month >= 0; $month--) {
            Transaction::factory()->income()->create([
                'user_id' => $user->id,
                'account_id' => $personalAccount->id,
                'category_id' => $incomeCategories->where('name', 'Sueldo')->first()?->id,
                'amount' => fake()->numberBetween(1800000, 2200000),
                'description' => 'Sueldo',
                'transaction_date' => now()->subMonths($month)->startOfMonth()->addDays(5),
            ]);
        }

        $merchants = [
            'Supermercado Lider',
            'Jumbo',
            'Starbucks',
            'Uber',
            'Copec',
            'Netflix',
            'Spotify',
            'VTR',
            'Enel',
        ];

        for ($i = 0; $i < 25; $i++) {
            $category = $expenseCategories->random();
            $daysAgo = fake()->numberBetween(0, 90);

            Transaction::factory()->expense()->create([
                'user_id' => $user->id,
                'account_id' => $personalAccount->id,
                'category_id' => $category->id,
                'amount' => $this->getRealisticAmount($category->name),
                'description' => $this->getDescriptionForCategory($category->name, $merchants),
                'transaction_date' => now()->subDays($daysAgo),
            ]);
        }

        for ($i = 0; $i < 10; $i++) {
            $category = $expenseCategories->random();
            $daysAgo = fake()->numberBetween(0, 90);

            Transaction::factory()->expense()->create([
                'user_id' => $user->id,
                'account_id' => $casaAccount->id,
                'category_id' => $category->id,
                'amount' => $this->getRealisticAmount($category->name),
                'description' => $this->getDescriptionForCategory($category->name, $merchants),
                'transaction_date' => now()->subDays($daysAgo),
            ]);
        }
    }

    private function getRealisticAmount(string $categoryName): int
    {
        return match ($categoryName) {
            'Alimentacion', 'Supermercado' => fake()->numberBetween(15000, 120000),
            'Transporte' => fake()->numberBetween(2000, 30000),
            'Entretenimiento' => fake()->numberBetween(5000, 50000),
            'Salud' => fake()->numberBetween(10000, 100000),
            'Servicios' => fake()->numberBetween(20000, 80000),
            'Educacion' => fake()->numberBetween(50000, 300000),
            'Ropa' => fake()->numberBetween(20000, 150000),
            'Restaurantes' => fake()->numberBetween(10000, 60000),
            'Tecnologia' => fake()->numberBetween(30000, 500000),
            'Hogar' => fake()->numberBetween(10000, 200000),
            'Mascotas' => fake()->numberBetween(15000, 80000),
            default => fake()->numberBetween(5000, 100000),
        };
    }

    private function getDescriptionForCategory(string $categoryName, array $merchants): ?string
    {
        return match ($categoryName) {
            'Alimentacion', 'Supermercado' => fake()->randomElement(['Supermercado Lider', 'Jumbo', 'Santa Isabel', 'Unimarc']),
            'Transporte' => fake()->randomElement(['Uber', 'Copec', 'Shell', 'Estacionamiento', 'Metro']),
            'Entretenimiento' => fake()->randomElement(['Netflix', 'Spotify', 'Cine', 'Steam', 'PlayStation Store']),
            'Servicios' => fake()->randomElement(['Enel', 'Aguas Andinas', 'Metrogas', 'VTR', 'Movistar']),
            'Restaurantes' => fake()->randomElement(['Starbucks', 'McDonald\'s', 'Restaurant', 'Cafe', 'Sushi']),
            'Salud' => fake()->randomElement(['Farmacia Ahumada', 'Cruz Verde', 'Consulta medica', 'Dentista']),
            'Ropa' => fake()->randomElement(['Falabella', 'Ripley', 'Paris', 'H&M', 'Zara']),
            'Tecnologia' => fake()->randomElement(['Amazon', 'MercadoLibre', 'PCFactory', 'Solotodo']),
            default => fake()->optional(0.7)->randomElement($merchants),
        };
    }
}
