<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'full_name' => $this->full_name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'icon' => $this->icon,
            'color' => $this->color,
            'is_system' => $this->is_system,
            'is_parent' => $this->isParent(),
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'parent' => $this->when(
                $this->relationLoaded('parent') && $this->parent,
                fn () => [
                    'id' => $this->parent->id,
                    'uuid' => $this->parent->uuid,
                    'name' => $this->parent->name,
                ]
            ),
            'children' => $this->when(
                $this->relationLoaded('children'),
                fn () => CategoryResource::collection($this->children)
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
