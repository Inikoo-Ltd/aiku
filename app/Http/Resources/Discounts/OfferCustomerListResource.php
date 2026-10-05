<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 02 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Discounts;

use App\Enums\CRM\Customer\CustomerStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string|null $code
 * @property string $shop_slug
 * @property string $organisation_slug
 * @property string $customer_slug
 * @property string $customer_reference
 * @property string $customer_name
 * @property string $customer_state
 * @property string|null $order_slug
 * @property string|null $order_reference
 * @property string|null $order_date
 */
class OfferCustomerListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'code'               => $this->code,
            'customer_name'      => $this->customer_name,
            'customer_reference' => $this->customer_reference,
            'customer_state'     => CustomerStateEnum::labels()[$this->customer_state] ?? $this->customer_state,
            'customer_route'     => [
                'name'       => 'grp.org.shops.show.crm.customers.show',
                'parameters' => [
                    'organisation' => $this->organisation_slug,
                    'shop'         => $this->shop_slug,
                    'customer'     => $this->customer_slug,
                ],
            ],
            'order_reference'    => $this->order_reference,
            'order_date'         => $this->order_date,
            'order_route'        => $this->order_slug ? [
                'name'       => 'grp.org.shops.show.ordering.orders.show',
                'parameters' => [
                    'organisation' => $this->organisation_slug,
                    'shop'         => $this->shop_slug,
                    'order'        => $this->order_slug,
                ],
            ] : null,
        ];
    }
}
