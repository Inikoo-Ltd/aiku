<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-13h-38m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Group\Resources;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicesApiResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {

        return [
            'id'                     => $this->id,
            'slug'                   => $this->slug,
            'reference'              => $this->reference,
            'total_amount'                   => $this->total_amount,
            'net_amount'           => $this->net_amount,
            'pay_status'               => $this->pay_status,
            'date'                  => $this->date,
            'type'                  => $this->type,
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
            'in_process'             => $this->in_process,
            'currency_code'             => $this->currency_code,
            'currency_symbol'             => $this->currency_symbol,
            'customer_name'             => $this->customer_name,
        ];
    }
}
