<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-11h-54m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\HasSelfCall;
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
class RetinaDropshippingOrdersInPlatformResources extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        if (!$this->order_state) {
            $stateIcons = [
                'state_label'              => OrderStateEnum::labels()[$this->state->value],
                'state_icon'               => OrderStateEnum::stateIcon()[$this->state->value]
            ];
        } else {
            $stateIcons = [
                'state_label'              => OrderStateEnum::labels()[$this->order_state],
                'state_icon'               => OrderStateEnum::stateIcon()[$this->order_state]
            ];
        }

        return [
            'id'                       => $this->id,
            'date'                     => $this->date,
            'platform_order_id'        => $this->platform_order_id,
            'reference'                => $this->reference,
            'slug'                     => $this->slug,
            'client_name'              => $this->client_name,
            'state'                    => $this->state,
            'total_amount'             => $this->total_amount,
            'payment_amount'           => $this->payment_amount,
            'is_fully_paid'            => $this->total_amount == $this->payment_amount,
            'number_item_transactions' => $this->number_item_transactions,
            'client_ulid'              => $this->client_ulid,
            ...$stateIcons
        ];
    }
}
