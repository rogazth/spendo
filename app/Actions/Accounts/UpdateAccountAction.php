<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Models\User;

class UpdateAccountAction
{
    public function handle(Account $account, User $user, array $data): Account
    {
        $isDefault = $data['is_default'] ?? $account->is_default;

        if ($isDefault && ! $account->is_default) {
            $user->accounts()->where('id', '!=', $account->id)->update(['is_default' => false]);
        }

        $allowedFields = ['name', 'currency', 'color', 'icon', 'is_active', 'is_default'];

        $updates = array_intersect_key($data, array_flip($allowedFields));

        $account->update($updates);

        return $account->fresh();
    }
}
