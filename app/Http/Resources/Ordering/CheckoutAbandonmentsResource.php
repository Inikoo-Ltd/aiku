<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 09 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Ordering;

use App\Enums\Ordering\CheckoutAbandonment\CheckoutAbandonmentStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $order_id
 * @property string $reference
 * @property string $order_slug
 * @property int $customer_id
 * @property string $customer_name
 * @property string $customer_slug
 * @property int $shop_id
 * @property string $shop_name
 * @property string $shop_code
 * @property string $shop_slug
 * @property string $organisation_name
 * @property string $organisation_code
 * @property string $organisation_slug
 * @property string $checkout_visited_at
 * @property string $total_amount
 * @property string $currency_code
 * @property int $number_items
 * @property string $state
 * @property string|null $recovered_at
 */
class CheckoutAbandonmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'order_id'            => $this->order_id,
            'reference'           => $this->reference,
            'order_slug'          => $this->order_slug,
            'customer_name'       => $this->customer_name,
            'customer_slug'       => $this->customer_slug,
            'shop_name'           => $this->shop_name,
            'shop_code'           => $this->shop_code,
            'shop_slug'           => $this->shop_slug,
            'organisation_name'   => $this->organisation_name,
            'organisation_code'   => $this->organisation_code,
            'organisation_slug'   => $this->organisation_slug,
            'checkout_visited_at' => $this->checkout_visited_at,
            'total_amount'        => $this->total_amount,
            'currency_code'       => $this->currency_code,
            'number_items'        => (int) $this->number_items,
            'state'               => $this->state->value,
            'state_label'         => CheckoutAbandonmentStateEnum::labels()[$this->state->value] ?? ucfirst($this->state->value),
            'recovered_at'        => $this->recovered_at,
        ];
    }
}
