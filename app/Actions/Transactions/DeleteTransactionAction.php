<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class DeleteTransactionAction
{
    public function handle(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            if ($transaction->linked_transaction_id !== null) {
                Transaction::query()->find($transaction->linked_transaction_id)?->delete();
            }

            $transaction->delete();
        });
    }
}
