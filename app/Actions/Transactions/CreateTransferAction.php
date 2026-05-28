<?php

namespace App\Actions\Transactions;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateTransferAction
{
    /**
     * Create a transfer as two linked transactions:
     *   - origin account: negative signed amount (outflow)
     *   - destination account: positive signed amount (inflow)
     *
     * Both rows are linked via `linked_transaction_id`.
     *
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

        $absoluteAmount = abs($data['amount']);

        [$transferOut, $transferIn] = DB::transaction(function () use ($user, $data, $originAccount, $destinationAccount, $absoluteAmount) {
            $transferOut = Transaction::create([
                'user_id' => $user->id,
                'account_id' => $originAccount->id,
                'type' => TransactionType::Transfer,
                'amount' => -$absoluteAmount,
                'currency' => $originAccount->currency,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'exclude_from_budget' => true,
                'transaction_date' => $data['transaction_date'] ?? now(),
            ]);

            $transferIn = Transaction::create([
                'user_id' => $user->id,
                'account_id' => $destinationAccount->id,
                'type' => TransactionType::Transfer,
                'linked_transaction_id' => $transferOut->id,
                'amount' => $absoluteAmount,
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
