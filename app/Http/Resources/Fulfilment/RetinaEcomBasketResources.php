<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $date
 * @property mixed $name
 * @property mixed $reference
 * @property mixed $slug
 * @property mixed $state
 * @property mixed $number_item_transactions
 */
class RetinaEcomBasketResources extends JsonResource
{
    public static $wrap = null;
    
    public function toArray($request): array
    {
        return [
            'net_amount'                => $this->net_amount,
            'gross_amount'              => $this->gross_amount,
            'tax_amount'                => $this->tax_amount,
            'goods_amount'              => $this->goods_amount,
            'services_amount'           => $this->services_amount,
            'charges_amount'            => $this->charges_amount,
        ];
    }
}
