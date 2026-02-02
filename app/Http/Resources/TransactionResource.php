<?php

namespace App\Http\Resources;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
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
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'type_icon' => $this->type->icon(),
            'is_debit' => $this->type->isDebit(),
            'amount' => $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'currency' => $this->currency,
            'currency_locale' => Currency::localeFor($this->currency),
            'description' => $this->description,
            'notes' => $this->notes,
            'transaction_date' => $this->transaction_date,
            'account_id' => $this->account_id,
            'payment_method_id' => $this->payment_method_id,
            'category_id' => $this->category_id,
            'linked_transaction_id' => $this->linked_transaction_id,
            'account' => new AccountResource($this->whenLoaded('account')),
            'payment_method' => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'linked_transaction' => new TransactionResource($this->whenLoaded('linkedTransaction')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
