<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Api;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $reference
 * @property string $state
 * @property float $net_amount
 * @property float $total_amount
 * @property string|\Illuminate\Support\Carbon|null $date
 * @property string|null $payment_status
 * @property string|null $payment_state
 * @property string $currency_code
 */
class OrdersResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'reference'      => $this->reference,
            'state'          => $this->state,
            'net_amount'     => $this->net_amount,
            'total_amount'   => $this->total_amount,
            'date'           => $this->date,
            'payment_status' => $this->payment_status,
            'payment_state'  => $this->payment_state,
            'currency_code'  => $this->currency_code,
        ];
    }
}
