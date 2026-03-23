<?php

namespace App\Actions\Accounts;

use App\Models\Account;

class DeleteAccountAction
{
    public function handle(Account $account): void
    {
        $account->forceDelete();
    }
}
