<?php

namespace App\Http\Resources;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
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
            'type_icon' => $this->type->icon(),
            'linked_account_id' => $this->linked_account_id,
            'currency' => $this->currency,
            'currency_locale' => Currency::localeFor($this->currency),
            'credit_limit' => $this->credit_limit,
            'billing_cycle_day' => $this->billing_cycle_day,
            'payment_due_day' => $this->payment_due_day,
            'color' => $this->color,
            'icon' => $this->icon,
            'last_four_digits' => $this->last_four_digits,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'sort_order' => $this->sort_order,
            'is_credit_card' => $this->isCreditCard(),
            'current_debt' => $this->current_debt,
            'available_credit' => $this->available_credit,
            'formatted_debt' => $this->formatted_debt,
            'transactions_count' => $this->whenCounted('transactions'),
            'linked_account' => new AccountResource($this->whenLoaded('linkedAccount')),
            'transactions' => TransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
