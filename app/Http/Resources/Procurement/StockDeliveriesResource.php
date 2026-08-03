<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $reference
 * @property mixed $parent_name
 */
class StockDeliveriesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'reference'   => $this->reference,
            'parent_name' => $this->parent_name,
        ];
    }
}
