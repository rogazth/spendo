<?php

namespace App\Actions\Categories;

use App\Models\Category;

class UpdateCategoryAction
{
    public function handle(Category $category, array $data): Category
    {
        if ($category->is_system) {
            throw new \InvalidArgumentException('Cannot edit system categories');
        }

        if (! empty($data['parent_id'])) {
            Category::findOrFail($data['parent_id']);
        }

        $category->update($data);

        return $category;
    }
}
