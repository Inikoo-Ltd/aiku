<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 09:40:37 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Ordering;

use App\Enums\Ordering\Order\OrderPayDetailedStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property OrderStateEnum $state
 * @property string $shop_slug
 * @property string $date
 * @property string $reference
 * @property mixed $net_amount
 * @property mixed $total_amount
 * @property mixed $customer_name
 * @property mixed $customer_slug
 * @property mixed $payment_state
 * @property mixed $payment_status
 * @property mixed $currency_code
 * @property mixed $currency_id
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $shop_name
 * @property mixed $organisation_code
 * @property mixed $shop_code
 * @property mixed $updated_by_customer_at
 * @property mixed $payment_amount
 * @property OrderPayDetailedStatusEnum $pay_detailed_status
 *
 */
class OrdersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                   => $this->slug,
            'reference'              => $this->reference,
            'date'                   => $this->date,
            'name'                   => $this->name,
            'state'                  => $this->state,
            'state_icon'             => $this->state->stateIcon()[$this->state->value],
            'net_amount'             => $this->net_amount,
            'payment_amount'         => $this->payment_amount,
            'total_amount'           => $this->total_amount,
            'customer_name'          => $this->customer_name,
            'customer_slug'          => $this->customer_slug,
            'payment_state'          => $this->payment_state,
            'payment_status'         => $this->payment_status,
            'pay_detailed_status'    => $this->pay_detailed_status ? $this->pay_detailed_status->labels()[$this->pay_detailed_status->value] : '',
            'currency_code'          => $this->currency_code,
            'currency_id'            => $this->currency_id,
            'organisation_name'      => $this->organisation_name,
            'organisation_code'      => $this->organisation_code,
            'organisation_slug'      => $this->organisation_slug,
            'shop_name'              => $this->shop_name,
            'shop_code'              => $this->shop_code,
            'shop_slug'              => $this->shop_slug,
            'created_at'             => $this->created_at,
            'is_premium_dispatch'    => $this->is_premium_dispatch,
            'updated_by_customer_at' => $this->updated_by_customer_at,
        ];
    }
}
