<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
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
            'user_id' => $this->user_id,
            'name' => $this->name,
            'description' => $this->description,
            'currency' => $this->currency,
            'frequency' => $this->frequency,
            'anchor_date' => $this->anchor_date?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'is_active' => $this->is_active,
            'total_budgeted' => $this->total_budgeted,
            'current_cycle_spent' => $this->when(
                isset($this->current_cycle_spent),
                $this->current_cycle_spent
            ),
            'current_cycle_percentage' => $this->when(
                isset($this->current_cycle_percentage),
                $this->current_cycle_percentage
            ),
            'current_cycle_start' => $this->when(
                isset($this->current_cycle_start),
                $this->current_cycle_start
            ),
            'current_cycle_end' => $this->when(
                isset($this->current_cycle_end),
                $this->current_cycle_end
            ),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'uuid' => $item->uuid,
                        'budget_id' => $item->budget_id,
                        'category_id' => $item->category_id,
                        'amount' => $item->amount,
                        'spent' => $item->spent ?? null,
                        'remaining' => $item->remaining ?? null,
                        'percentage' => $item->percentage ?? null,
                        'category' => $item->category ? [
                            'id' => $item->category->id,
                            'uuid' => $item->category->uuid,
                            'name' => $item->category->name,
                            'color' => $item->category->color,
                            'parent_id' => $item->category->parent_id,
                        ] : null,
                    ];
                })->values();
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
