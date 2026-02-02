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
            'default_account_id' => $this->default_account_id,
            'default_payment_method_id' => $this->default_payment_method_id,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'date_format' => $this->date_format,
            'time_format' => $this->time_format,
            'first_day_of_week' => $this->first_day_of_week,
            'budget_cycle_start_day' => $this->budget_cycle_start_day,
            'default_account' => new AccountResource($this->whenLoaded('defaultAccount')),
            'default_payment_method' => new PaymentMethodResource($this->whenLoaded('defaultPaymentMethod')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
