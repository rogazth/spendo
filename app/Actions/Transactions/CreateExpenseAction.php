<?php

namespace App\Actions\Transactions;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CreateExpenseAction
{
    public function handle(User $user, array $data): Transaction
    {
        $account = $user->accounts()->find($data['account_id'] ?? null);

        if (! $account) {
            throw new ModelNotFoundException('Account not found.');
        }

        $category = null;

        if (! empty($data['category_id'])) {
            $category = Category::where(function ($q) use ($user) {
                $q->whereNull('user_id')->orWhere('user_id', $user->id);
            })->find($data['category_id']);

            if (! $category) {
                throw new ModelNotFoundException('Category not found or not accessible.');
            }

        }

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Expense,
            'account_id' => $account->id,
            'category_id' => $category?->id,
            'amount' => $data['amount'],
            'currency' => $account->currency,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'exclude_from_budget' => $data['exclude_from_budget'] ?? false,
            'transaction_date' => $data['transaction_date'] ?? now(),
        ]);

        if (array_key_exists('tag_ids', $data)) {
            $transaction->tags()->sync($data['tag_ids'] ?? []);
        }

        return $transaction;
    }
}
