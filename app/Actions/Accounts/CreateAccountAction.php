<?php

namespace App\Actions\Accounts;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateAccountAction
{
    public function handle(User $user, array $data): Account
    {
        return DB::transaction(function () use ($user, $data) {
            $isDefault = $data['is_default'] ?? false;

            if ($isDefault) {
                $user->accounts()->update(['is_default' => false]);
            }

            $account = $user->accounts()->create([
                'name' => $data['name'],
                'currency' => $data['currency'],
                'color' => $data['color'] ?? '#3B82F6',
                'emoji' => $data['emoji'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_default' => $isDefault,
            ]);

            $initialBalance = $data['initial_balance'] ?? 0;

            if ($initialBalance > 0) {
                $categoryId = Category::where('is_system', true)
                    ->first()?->id;

                Transaction::create([
                    'user_id' => $user->id,
                    'type' => TransactionType::Income,
                    'account_id' => $account->id,
                    'category_id' => $categoryId,
                    'amount' => $initialBalance,
                    'currency' => $account->currency,
                    'description' => 'Balance inicial',
                    'transaction_date' => now(),
                    'exclude_from_budget' => true,
                ]);
            }

            return $account;
        });
    }
}
