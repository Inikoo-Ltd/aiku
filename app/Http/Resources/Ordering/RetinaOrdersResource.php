<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Ordering;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $reference
 * @property mixed $net_amount
 * @property mixed $total_amount
 * @property mixed $client_ulid
 * @property mixed $client_name
 * @property mixed $created_at
 * @property mixed $state
 * @property mixed $id
 */
class RetinaOrdersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'reference' => $this->reference,
            'net_amount' => $this->net_amount,
            'total_amount' => $this->total_amount,
            'client_name' => $this->client_name,
            'client_ulid' => $this->client_ulid,
            'created_at' => $this->created_at,
            'number_item_transactions' => $this->number_item_transactions ?? 0,
            'delete_route' => $this->state == OrderStateEnum::CREATING ? [
                'name' => 'retina.models.order.delete_basket',
                'parameters' => [
                    'order' => $this->id,
                ],
                'method' => 'delete',
            ] : null,
        ];
    }
}
