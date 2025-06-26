<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-11h-54m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

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
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'date'                     => $this->date,
            'platform_order_id'        => match ($this->platform_type) {
                PlatformTypeEnum::SHOPIFY->value => Arr::get($this->data, 'order_id'),
                default => $this->platform_order_id
            },
            'reference'                => $this->reference,
            'slug'                     => $this->slug,
            'client_name'              => $this->client_name,
            'state'                    => $this->state,
            'total_amount'             => $this->total_amount,
            'number_item_transactions' => $this->number_item_transactions,
            'state_label'              => OrderStateEnum::labels()[$this->state->value],
            'state_icon'               => OrderStateEnum::stateIcon()[$this->state->value]
        ];
    }
}
