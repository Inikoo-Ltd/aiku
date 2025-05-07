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
use Illuminate\Support\Arr;

/**
 * @property mixed $id
 * @property mixed $date
 * @property mixed $name
 * @property mixed $reference
 * @property mixed $slug
 * @property mixed $state
 * @property mixed $number_item_transactions
 */
class RetinaTopupResources extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'reference'                => $this->reference,
            'amount'                => $this->amount,
            'status'                => $this->status,
            'payment_url'                => Arr::get($this->payment, 'data.payment_url'),
        ];
    }
}
