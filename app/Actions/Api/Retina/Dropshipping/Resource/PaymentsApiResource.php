<?php

/*
 * author Arya Permana - Kirin
 * created on 26-06-2025-12h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Dropshipping\Resource;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentsApiResource extends JsonResource
{
    public function toArray($request): array
    {
        return array(
            'id'         => $this->id,
            'status'     => $this->status,
            'type'      => $this->type,
            'payment_account' => $this->paymentAccount->name,
            'date'       => $this->date,
            'reference'  => $this->reference,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'amount'     => $this->amount,
            'currency' => $this->currency->code,
        );
    }
}
