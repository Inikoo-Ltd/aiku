<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Group\Resources;

use App\Http\Resources\HasSelfCall;
use App\Models\CRM\Customer;
use Illuminate\Http\Resources\Json\JsonResource;

class OrdersApiResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Customer $customer */
        $order = $this;

        return [
            'id'            => $order->id,
            'reference'     => $order->reference,
            'state'         => $order->state,
            'net_amount'    => $order->net_amount,
            'total_amount'    => $order->total_amount,
            'date'          => $order->date,
            'payment_status'       => $order->payment_status,
            'payment_state'        => $order->payment_state,
            'currency_code' => $order->currency_code,
        ];
    }
}
