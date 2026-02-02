<?php

namespace App\Http\Resources;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
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
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'currency' => $this->currency,
            'currency_locale' => Currency::localeFor($this->currency),
            'current_balance' => $this->current_balance,
            'formatted_balance' => $this->formatted_balance,
            'color' => $this->color,
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'sort_order' => $this->sort_order,
            'payment_methods_count' => $this->whenCounted('paymentMethods'),
            'payment_methods' => PaymentMethodResource::collection($this->whenLoaded('paymentMethods')),
            'transactions' => TransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
