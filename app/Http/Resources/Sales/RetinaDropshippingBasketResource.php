<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Jun 2025 13:37:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Sales;

use App\Models\Ordering\Order;
use Illuminate\Http\Resources\Json\JsonResource;

class RetinaDropshippingBasketResource extends JsonResource
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
        ];
    }
}
