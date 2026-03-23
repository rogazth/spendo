<?php

namespace App\Actions\Transactions;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class UpdateTransactionAction
{
    public function handle(Transaction $transaction, User $user, array $data): Transaction
    {
        if ($transaction->isTransfer()) {
            throw new InvalidArgumentException('Transfer transactions cannot be updated. Delete and recreate them instead.');
        }

        $updates = [];

        if (array_key_exists('description', $data) && $data['description'] !== null) {
            $updates['description'] = $data['description'];
        }

        if (array_key_exists('amount', $data) && $data['amount'] !== null) {
            $updates['amount'] = $data['amount'];
        }

        if (array_key_exists('transaction_date', $data) && $data['transaction_date'] !== null) {
            $updates['transaction_date'] = $data['transaction_date'];
        }

        if (array_key_exists('exclude_from_budget', $data) && $data['exclude_from_budget'] !== null) {
            $updates['exclude_from_budget'] = $data['exclude_from_budget'];
        }

        if (array_key_exists('notes', $data) && $data['notes'] !== null) {
            $updates['notes'] = $data['notes'];
        }

        if (! empty($data['category_id'])) {
            $expectedType = $transaction->type === \App\Enums\TransactionType::Income
                ? CategoryType::Income
                : CategoryType::Expense;

            $category = Category::where(function ($q) use ($user) {
                $q->whereNull('user_id')->orWhere('user_id', $user->id);
            })->find($data['category_id']);

            if (! $category) {
                throw new ModelNotFoundException('Category not found or not accessible.');
            }

            if ($category->type !== $expectedType) {
                throw new InvalidArgumentException("Use a {$expectedType->value} category for this transaction type.");
            }

            $updates['category_id'] = $category->id;
        }

        if (! empty($data['account_id'])) {
            $account = $user->accounts()->find($data['account_id']);

            if (! $account) {
                throw new ModelNotFoundException('Account not found.');
            }

            $updates['account_id'] = $account->id;
        }

        if (! empty($updates)) {
            $transaction->update($updates);
        }

        if (array_key_exists('tag_ids', $data)) {
            $transaction->tags()->sync($data['tag_ids'] ?? []);
        }

        return $transaction->fresh();
    }
}
