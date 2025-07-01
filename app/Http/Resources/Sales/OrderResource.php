<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Sales;

use App\Models\Ordering\Order;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Order $order */
        $order          = $this;

        return [
            'id'            => $order->id,
            'reference'     => $order->reference,
            'slug'          => $order->slug,
            'state'         => $order->state->value,
            'state_label'   => $order->state->labels()[$order->state->value],
            'state_icon'    => $order->state->stateIcon()[$order->state->value],
            'public_notes'  => $order->public_notes,
            'payment_amount'  => $order->payment_amount,
            'total_amount'  => $order->total_amount,
            'is_fully_paid' => $order->total_amount == $order->payment_amount,
            'unpaid_amount' => $order->total_amount - $order->payment_amount,
            // 'route_to_pay_unpaid' => {   // TODO and remove this
            //     'name' => 'sales.orders.pay',
            //     'params' => [
            //         'order' => $order->id,
            //     ],
            // },
        ];
    }
}
