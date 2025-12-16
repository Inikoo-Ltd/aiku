<?php

/*
 * author Arya Permana - Kirin
 * created on 16-10-2024-10h-59m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $code
 * @property mixed $name
 * @property mixed $available_quantity
 * @property mixed $price
 * @property mixed $rrp
 * @property mixed $state
 * @property mixed $status
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $units
 * @property mixed $unit
 * @property mixed $canonical_url
 * @property mixed $web_images
 * @property mixed $slug
 */
class CustomerBackInStockRemindersResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'name'           => $this->name,
            'stock'          => $this->available_quantity,
            'price'          => $this->price,
            'rrp'            => $this->rrp,
            'state'          => $this->state,
            'status'         => $this->status,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'units'          => $this->units,
            'unit'           => $this->unit,
            'url'            => $this->canonical_url,
            'web_images'     => $this->web_images,
            'transaction_id' => $this->transaction_id ?? null,
            'product_slug'   => $this->slug,
        ];
    }
}
