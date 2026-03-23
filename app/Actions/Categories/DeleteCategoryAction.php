<?php

namespace App\Actions\Categories;

use App\Models\Category;

class DeleteCategoryAction
{
    public function handle(Category $category): void
    {
        if ($category->is_system) {
            throw new \InvalidArgumentException('Cannot delete system categories');
        }

        $category->children()->update(['parent_id' => null]);
        $category->delete();
    }
}
