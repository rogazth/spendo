<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;

class CreateCategoryAction
{
    public function handle(User $user, array $data): Category
    {
        if (! empty($data['parent_id'])) {
            $parent = Category::findOrFail($data['parent_id']);
            $data['type'] = $parent->type->value;
        }

        return Category::create([
            ...$data,
            'user_id' => $user->id,
            'is_system' => false,
        ]);
    }
}
