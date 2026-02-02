<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentMethod;
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

        // User 1: Main user with lots of data
        $mainUser = User::factory()->create([
            'name' => 'Gabriel Rodriguez',
            'email' => 'gabriel98rl@gmail.com',
            'password' => Hash::make('luis1998'),
        ]);

        $this->seedUserWithLotsOfData($mainUser, $expenseCategories, $incomeCategories);

        // User 2: User with moderate data
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

    private function seedUserWithLotsOfData(User $user, $expenseCategories, $incomeCategories): void
    {
        // Create accounts
        $checkingAccount = Account::factory()->checking()->default()->create([
            'user_id' => $user->id,
            'name' => 'Cuenta Corriente Santander',
            'color' => '#EF4444',
        ]);

        $savingsAccount = Account::factory()->savings()->create([
            'user_id' => $user->id,
            'name' => 'Cuenta Ahorro BCI',
            'color' => '#3B82F6',
        ]);

        $cashAccount = Account::factory()->cash()->create([
            'user_id' => $user->id,
            'name' => 'Efectivo',
            'color' => '#10B981',
        ]);

        $investmentAccount = Account::factory()->investment()->create([
            'user_id' => $user->id,
            'name' => 'Fondo Mutuo Santander',
            'color' => '#8B5CF6',
        ]);

        // Create payment methods
        $creditCard = PaymentMethod::factory()->creditCard()->default()->create([
            'user_id' => $user->id,
            'name' => 'Visa Santander',
            'credit_limit' => 3000000,
            'billing_cycle_day' => 15,
            'payment_due_day' => 5,
            'last_four_digits' => '4532',
            'color' => '#EF4444',
        ]);

        $debitCard = PaymentMethod::factory()->debitCard()->create([
            'user_id' => $user->id,
            'name' => 'Debito Santander',
            'linked_account_id' => $checkingAccount->id,
            'last_four_digits' => '7891',
            'color' => '#3B82F6',
        ]);

        $cashPayment = PaymentMethod::factory()->cash()->create([
            'user_id' => $user->id,
            'name' => 'Efectivo',
            'linked_account_id' => $cashAccount->id,
            'color' => '#10B981',
        ]);

        $transfer = PaymentMethod::factory()->transfer()->create([
            'user_id' => $user->id,
            'name' => 'Transferencia',
            'linked_account_id' => $checkingAccount->id,
            'color' => '#F59E0B',
        ]);

        // Create income transactions (salary and others)
        for ($month = 5; $month >= 0; $month--) {
            // Monthly salary
            Transaction::factory()->income()->create([
                'user_id' => $user->id,
                'account_id' => $checkingAccount->id,
                'category_id' => $incomeCategories->where('name', 'Sueldo')->first()?->id,
                'amount' => fake()->numberBetween(2500000, 3500000),
                'description' => 'Sueldo mensual',
                'transaction_date' => now()->subMonths($month)->startOfMonth()->addDays(fake()->numberBetween(1, 5)),
            ]);

            // Random bonus/extra income
            if (fake()->boolean(30)) {
                Transaction::factory()->income()->create([
                    'user_id' => $user->id,
                    'account_id' => $checkingAccount->id,
                    'category_id' => $incomeCategories->random()->id,
                    'amount' => fake()->numberBetween(100000, 500000),
                    'description' => fake()->randomElement(['Freelance', 'Venta', 'Reembolso', 'Bono']),
                    'transaction_date' => now()->subMonths($month)->addDays(fake()->numberBetween(1, 28)),
                ]);
            }
        }

        // Create expense transactions (150+ transactions over 6 months)
        $merchants = [
            'Supermercado Lider', 'Jumbo', 'Santa Isabel', 'Starbucks', 'McDonald\'s',
            'Uber', 'Uber Eats', 'Rappi', 'Copec', 'Shell', 'Farmacias Ahumada',
            'Falabella', 'Ripley', 'Paris', 'Netflix', 'Spotify', 'Amazon',
            'MercadoLibre', 'VTR', 'Movistar', 'Enel', 'Aguas Andinas', 'Metrogas',
        ];

        $paymentMethods = [$creditCard, $debitCard, $cashPayment, $transfer];

        for ($i = 0; $i < 180; $i++) {
            $paymentMethod = fake()->randomElement($paymentMethods);
            $category = $expenseCategories->random();
            $daysAgo = fake()->numberBetween(0, 180);

            Transaction::factory()->expense()->create([
                'user_id' => $user->id,
                'account_id' => $checkingAccount->id,
                'payment_method_id' => $paymentMethod->id,
                'category_id' => $category->id,
                'amount' => $this->getRealisticAmount($category->name),
                'description' => $this->getDescriptionForCategory($category->name, $merchants),
                'transaction_date' => now()->subDays($daysAgo),
            ]);
        }

        // Create some credit card settlements
        for ($month = 4; $month >= 0; $month--) {
            Transaction::factory()->settlement()->create([
                'user_id' => $user->id,
                'account_id' => $checkingAccount->id,
                'payment_method_id' => $creditCard->id,
                'category_id' => null,
                'amount' => fake()->numberBetween(200000, 800000),
                'description' => 'Pago tarjeta de credito',
                'transaction_date' => now()->subMonths($month)->day(5),
            ]);
        }
    }

    private function seedUserWithModerateData(User $user, $expenseCategories, $incomeCategories): void
    {
        // Create accounts
        $checkingAccount = Account::factory()->checking()->default()->create([
            'user_id' => $user->id,
            'name' => 'Cuenta Corriente BCI',
            'color' => '#3B82F6',
        ]);

        $savingsAccount = Account::factory()->savings()->create([
            'user_id' => $user->id,
            'name' => 'Cuenta Ahorro',
            'color' => '#10B981',
        ]);

        // Create payment methods
        $debitCard = PaymentMethod::factory()->debitCard()->default()->create([
            'user_id' => $user->id,
            'name' => 'Debito BCI',
            'linked_account_id' => $checkingAccount->id,
            'last_four_digits' => '1234',
            'color' => '#3B82F6',
        ]);

        $creditCard = PaymentMethod::factory()->creditCard()->create([
            'user_id' => $user->id,
            'name' => 'Visa BCI',
            'credit_limit' => 2000000,
            'billing_cycle_day' => 20,
            'payment_due_day' => 10,
            'last_four_digits' => '5678',
            'color' => '#8B5CF6',
        ]);

        // Create income (last 3 months)
        for ($month = 2; $month >= 0; $month--) {
            Transaction::factory()->income()->create([
                'user_id' => $user->id,
                'account_id' => $checkingAccount->id,
                'category_id' => $incomeCategories->where('name', 'Sueldo')->first()?->id,
                'amount' => fake()->numberBetween(1800000, 2200000),
                'description' => 'Sueldo',
                'transaction_date' => now()->subMonths($month)->startOfMonth()->addDays(5),
            ]);
        }

        // Create expenses (30-40 transactions over 3 months)
        $merchants = [
            'Supermercado Lider', 'Jumbo', 'Starbucks', 'Uber', 'Copec',
            'Netflix', 'Spotify', 'VTR', 'Enel',
        ];

        $paymentMethods = [$debitCard, $creditCard];

        for ($i = 0; $i < 35; $i++) {
            $paymentMethod = fake()->randomElement($paymentMethods);
            $category = $expenseCategories->random();
            $daysAgo = fake()->numberBetween(0, 90);

            Transaction::factory()->expense()->create([
                'user_id' => $user->id,
                'account_id' => $checkingAccount->id,
                'payment_method_id' => $paymentMethod->id,
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
