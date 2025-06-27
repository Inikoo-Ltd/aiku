<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Fulfilment\Resource;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $reference
 * @property mixed $state
 * @property mixed $type
 * @property mixed $customer_reference
 * @property mixed $number_pallets
 * @property mixed $number_services
 * @property mixed $number_stored_items
 * @property mixed $number_physical_goods
 * @property mixed $total_amount
 * @property mixed $currency_code
 * @property mixed $date
 */
class SKUOrderApiResource extends JsonResource
{
    public function toArray($request): array
    {

        return [
            'id'                    => $this->id,
            'stored_item_id'        => $this->stored_item_id,
            'reference'             => $this->reference,
            'slug'                  => $this->slug,
            'name'                  => $this->name,
            'quantity_ordered'      => (int) $this->quantity_ordered,
            'total_quantity'        => (int) $this->total_quantity,
        ];
    }
}
