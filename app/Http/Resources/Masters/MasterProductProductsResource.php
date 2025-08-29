<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-11h-52m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Masters;

use App\Http\Resources\Goods\TradeUnitsForMasterResource;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterProductProductsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'product_id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'shop_id'   => $this->shop?->id,
            'shop_name' => $this->shop?->name,
            'shop_code' => $this->shop?->code,
            'shop_currency' => $this->shop?->currency?->code,
            'price' => $this->price,
            'update_route' => [
                'name' => 'grp.models.product.update',
                'parameters' => [
                    'product' => $this->id
                ]
            ]
        ];
    }
}
