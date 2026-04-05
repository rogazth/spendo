<?php

namespace App\Actions\Transactions;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateTransferAction
{
    /**
     * @return array{0: Transaction, 1: Transaction}
     */
    public function handle(User $user, array $data): array
    {
        $originAccount = $user->accounts()->find($data['origin_account_id'] ?? null);

        if (! $originAccount) {
            throw new ModelNotFoundException('Origin account not found.');
        }

        $destinationAccount = $user->accounts()->find($data['destination_account_id'] ?? null);

        if (! $destinationAccount) {
            throw new ModelNotFoundException('Destination account not found.');
        }

        if ($originAccount->id === $destinationAccount->id) {
            throw new InvalidArgumentException('Origin and destination accounts must be different.');
        }

        $transferCategory = Category::where('is_system', true)
            ->where('name', 'Transferencia')
            ->first();

        [$transferOut, $transferIn] = DB::transaction(function () use ($user, $data, $originAccount, $destinationAccount, $transferCategory) {
            $transferOut = Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::TransferOut,
                'account_id' => $originAccount->id,
                'category_id' => $transferCategory?->id,
                'amount' => $data['amount'],
                'currency' => $originAccount->currency,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'exclude_from_budget' => true,
                'transaction_date' => $data['transaction_date'] ?? now(),
            ]);

            $transferIn = Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::TransferIn,
                'account_id' => $destinationAccount->id,
                'category_id' => $transferCategory?->id,
                'linked_transaction_id' => $transferOut->id,
                'amount' => $data['amount'],
                'currency' => $destinationAccount->currency,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'exclude_from_budget' => true,
                'transaction_date' => $data['transaction_date'] ?? now(),
            ]);

            $transferOut->update(['linked_transaction_id' => $transferIn->id]);

            return [$transferOut, $transferIn];
        });

        if (array_key_exists('tag_ids', $data)) {
            $transferOut->tags()->sync($data['tag_ids'] ?? []);
        }

        return [$transferOut, $transferIn];
    }
}
