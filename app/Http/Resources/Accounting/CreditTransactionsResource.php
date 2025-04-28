<?php

/*
 * author Arya Permana - Kirin
 * created on 28-04-2025-14h-35m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

class CreditTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'type'           => $this->type,
            'amount'        => $this->amount,
            'running_amount'          => $this->running_amount,
            'payment_reference'                => $this->payment_reference,
            'payment_type'                => $this->payment_type,
            'currency_code' => $this->currency_code,
        ];
    }
}
