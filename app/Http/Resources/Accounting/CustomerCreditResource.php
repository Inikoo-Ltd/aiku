<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerCreditResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'slug'                     => $this->slug,
            'reference'                => $this->reference,
            'name'                     => $this->name,
            'email'                    => $this->email,
            'shop_code'                => $this->shop_code,
            'credit_balance'           => $this->credit_balance,
            'currency_symbol'          => $this->currency_symbol,
            'currency_code'            => $this->currency_code,
            'latest_transaction_date'  => $this->latest_transaction_date,
        ];
    }
}
