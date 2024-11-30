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
            'state'         => $order->state->value,
            'state_label'   => $order->state->labels()[$order->state->value],
            'state_icon'    => $order->state->stateIcon()[$order->state->value],
        ];
    }
}
