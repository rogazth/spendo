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
            $parent = Category::findOrFail($data['parent_id']);
            $data['type'] = $parent->type->value;
        }

        $category->update($data);

        return $category;
    }
}
