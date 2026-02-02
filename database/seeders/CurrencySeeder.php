<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'CLP', 'name' => 'Peso chileno', 'locale' => 'es-CL'],
            ['code' => 'USD', 'name' => 'Dólar estadounidense', 'locale' => 'en-US'],
            ['code' => 'EUR', 'name' => 'Euro', 'locale' => 'es-ES'],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
