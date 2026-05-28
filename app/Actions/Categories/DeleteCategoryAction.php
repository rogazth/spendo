<?php

namespace App\Actions\Categories;

use App\Models\Category;

class DeleteCategoryAction
{
    public function handle(Category $category): void
    {
        $category->children()->update(['parent_id' => null]);
        $category->delete();
    }
}
