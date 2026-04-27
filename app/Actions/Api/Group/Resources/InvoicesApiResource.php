<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-13h-38m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Group\Resources;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $reference
 * @property mixed $total_amount
 * @property mixed $net_amount
 * @property mixed $pay_status
 * @property mixed $date
 * @property mixed $type
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $in_process
 * @property mixed $currency_code
 * @property mixed $currency_symbol
 * @property mixed $customer_name
 */
class InvoicesApiResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'reference'       => $this->reference,
            'total_amount'    => $this->total_amount,
            'net_amount'      => $this->net_amount,
            'pay_status'      => $this->pay_status,
            'date'            => $this->date,
            'type'            => $this->type,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
            'in_process'      => $this->in_process,
            'currency_code'   => $this->currency_code,
            'currency_symbol' => $this->currency_symbol,
            'customer_name'   => $this->customer_name,
        ];
    }
}
