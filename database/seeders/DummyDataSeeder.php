<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Instrument;
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
        $mainUser = User::factory()->create([
            'name' => 'Gabriel Rodriguez',
            'email' => 'gabriel98rl@gmail.com',
            'password' => Hash::make('luis1998'),
        ]);

        // $this->seedUserWithModerateData($mainUser, $expenseCategories, $incomeCategories);

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
        // Create logical accounts
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

        // Create instruments
        $mercadoPago = Instrument::factory()->savings()->create([
            'user_id' => $user->id,
            'name' => 'Mercado Pago',
            'color' => '#3B82F6',
        ]);

        $creditCard = Instrument::factory()->creditCard()->create([
            'user_id' => $user->id,
            'name' => 'Visa Santander',
            'credit_limit' => 2000000,
            'billing_cycle_day' => 20,
            'payment_due_day' => 10,
            'last_four_digits' => '5678',
            'color' => '#8B5CF6',
        ]);

        // Create income (last 3 months) to Personal account
        for ($month = 2; $month >= 0; $month--) {
            Transaction::factory()->income()->create([
                'user_id' => $user->id,
                'account_id' => $personalAccount->id,
                'instrument_id' => $mercadoPago->id,
                'category_id' => $incomeCategories->where('name', 'Sueldo')->first()?->id,
                'amount' => fake()->numberBetween(1800000, 2200000),
                'description' => 'Sueldo',
                'transaction_date' => now()->subMonths($month)->startOfMonth()->addDays(5),
            ]);
        }

        // Create personal expenses
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

        $instruments = [$mercadoPago, $creditCard];

        for ($i = 0; $i < 25; $i++) {
            $instrument = fake()->randomElement($instruments);
            $category = $expenseCategories->random();
            $daysAgo = fake()->numberBetween(0, 90);

            Transaction::factory()->expense()->create([
                'user_id' => $user->id,
                'account_id' => $personalAccount->id,
                'instrument_id' => $instrument->id,
                'category_id' => $category->id,
                'amount' => $this->getRealisticAmount($category->name),
                'description' => $this->getDescriptionForCategory($category->name, $merchants),
                'transaction_date' => now()->subDays($daysAgo),
            ]);
        }

        // Casa expenses (using credit card across both accounts)
        for ($i = 0; $i < 10; $i++) {
            $category = $expenseCategories->random();
            $daysAgo = fake()->numberBetween(0, 90);

            Transaction::factory()->expense()->create([
                'user_id' => $user->id,
                'account_id' => $casaAccount->id,
                'instrument_id' => $creditCard->id,
                'category_id' => $category->id,
                'amount' => $this->getRealisticAmount($category->name),
                'description' => $this->getDescriptionForCategory($category->name, $merchants),
                'transaction_date' => now()->subDays($daysAgo),
            ]);
        }

        // Credit card settlement (instrument-only, no account impact)
        Transaction::factory()->settlement()->create([
            'user_id' => $user->id,
            'account_id' => null,
            'instrument_id' => $creditCard->id,
            'from_instrument_id' => $mercadoPago->id,
            'amount' => fake()->numberBetween(200000, 500000),
            'description' => 'Pago tarjeta Visa Santander',
            'transaction_date' => now()->subDays(5),
        ]);
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
