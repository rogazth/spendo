<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;

class CreateCategoryAction
{
    public function handle(User $user, array $data): Category
    {
        if (! empty($data['parent_id'])) {
            Category::findOrFail($data['parent_id']);
        }

        return Category::create([
            ...$data,
            'user_id' => $user->id,
            'is_system' => false,
        ]);
    }
}
