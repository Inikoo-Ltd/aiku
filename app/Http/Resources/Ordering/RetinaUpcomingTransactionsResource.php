<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $customer_id
 * @property mixed $product_id
 * @property mixed $order_id
 * @property mixed $transaction_id
 * @property mixed $quantity
 * @property mixed $public_notes
 * @property mixed $private_notes
 * @property mixed $type
 * @property mixed $state
 * @property mixed $product_code
 * @property mixed $product_name
 * @property mixed $created_at
 * @property mixed $updated_at
 */
class RetinaUpcomingTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'customer_id'    => $this->customer_id,
            'product_id'     => $this->product_id,
            'product_code'   => $this->product_code,
            'product_name'   => $this->product_name,
            'order_id'       => $this->order_id,
            'transaction_id' => $this->transaction_id,
            'quantity'       => $this->quantity,
            'public_notes'   => $this->public_notes,
            'private_notes'  => $this->private_notes,
            'type'           => $this->type,
            'state'          => $this->state,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at
        ];
    }
}
