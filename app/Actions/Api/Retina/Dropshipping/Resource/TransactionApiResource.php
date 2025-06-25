<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Resource;

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
 * @property mixed $id
 * @property mixed $quantity_ordered
 * @property mixed $status
 * @property mixed $quantity_bonus
 * @property mixed $quantity_dispatched
 * @property mixed $quantity_fail
 * @property mixed $quantity_cancelled
 * @property mixed $gross_amount
 * @property mixed $net_amount
 * @property string $asset_code
 * @property string $asset_name
 * @property string $asset_type
 * @property string $product_slug
 * @property string $currency_code
 * @property mixed $order_id
 * @property mixed $price
 */
class TransactionApiResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'state'               => $this->state,
            'status'              => $this->status,
            'quantity_ordered'    => intVal($this->quantity_ordered),
            'quantity_bonus'      => intVal($this->quantity_bonus),
            'quantity_dispatched' => intVal($this->quantity_dispatched),
            'quantity_fail'       => intVal($this->quantity_fail),
            'quantity_cancelled'  => intVal($this->quantity_cancelled),
            'gross_amount'        => $this->gross_amount,
            'net_amount'          => $this->net_amount,
            'price'               => $this->price,
            'asset_code'          => $this->asset_code,
            'asset_name'          => $this->asset_name,
            'asset_type'          => $this->asset_type,
            'product_slug'        => $this->product_slug,
            'created_at'          => $this->created_at,
            'currency_code'       => $this->currency_code,
        ];
    }
}
