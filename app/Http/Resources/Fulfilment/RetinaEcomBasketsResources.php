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
class RetinaEcomBasketsResources extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'product_code'                => $this->product_code,
            'product_name'                => $this->product_name,
            'quantity'                 => $this->quantity,
            'net_amount'                => $this->net_amount,
            'date'                => $this->date,
        ];
    }
}
