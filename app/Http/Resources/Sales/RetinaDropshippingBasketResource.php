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
            'id'                        => $order->id,
            'reference'                 => $order->reference,
            'slug'                      => $order->slug,
            'is_premium_dispatch'       => $order->is_premium_dispatch,
            'has_extra_packing'         => $order->has_extra_packing,
            'has_insurance'             => $order->has_insurance,
            'state'                     => $order->state->value,
            'state_label'               => $order->state->labels()[$order->state->value],
            'state_icon'                => $order->state->stateIcon()[$order->state->value],
            'customer_notes'            => $order->customer_notes,
            'shipping_notes'            => $order->shipping_notes,
            'public_notes'              => $order->public_notes,
            'is_collection'             => (bool) $order->collection_address_id
        ];
    }
}
