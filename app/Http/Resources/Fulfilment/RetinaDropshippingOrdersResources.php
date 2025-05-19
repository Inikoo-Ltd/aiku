<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-11h-54m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $date
 * @property mixed $name
 * @property mixed $reference
 * @property mixed $slug
 * @property mixed $state
 * @property mixed $number_item_transactions
 * @property mixed $client_name
 * @property mixed $total_amount
 * @property mixed $platform_name
 */
class RetinaDropshippingOrdersResources extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'date'                     => $this->date,
            'platform_order_id'        => $this->platform_order_id,
            'reference'                => $this->reference,
            'slug'                     => $this->slug,
            'client_name'              => $this->client_name,
            'state'                    => $this->state,
            'total_amount'             => $this->total_amount,
            'number_item_transactions' => $this->number_item_transactions,
            'state_label'              => OrderStateEnum::labels()[$this->order_state],
            'state_icon'               => OrderStateEnum::stateIcon()[$this->order_state]
        ];
    }
}
