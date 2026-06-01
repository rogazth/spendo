<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSettingsResource extends JsonResource
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
            'default_currency' => $this->default_currency,
            'timezone' => $this->timezone,
            'budget_cycle_start_day' => $this->budget_cycle_start_day,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
