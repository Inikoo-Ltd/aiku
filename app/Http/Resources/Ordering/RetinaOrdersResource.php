<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property string $state
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
 *
 */
class RetinaOrdersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                   => $this->slug,
            'reference'              => $this->reference,
            'net_amount'             => $this->net_amount,
            'total_amount'           => $this->total_amount,
            'client_name'            => $this->client_name,
            'client_ulid'            => $this->client_ulid,
            'created_at'             => $this->created_at,
            'number_item_transactions'                  => $this->number_item_transactions ?? 0,
        ];
    }
}
