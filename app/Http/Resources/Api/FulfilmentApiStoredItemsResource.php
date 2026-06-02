<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 31 May 2026 01:47:45 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $item_id
 * @property mixed $stored_items_slug
 * @property mixed $stored_items_reference
 * @property mixed $stored_items_name
 * @property mixed $total_quantity
 * @property mixed $item_type
 * @property mixed $created_at
 * @property mixed $updated_at
 *
 */
class FulfilmentApiStoredItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'item_id'       => $this->item_id,
            'slug'          => $this->stored_items_slug,
            'code'          => $this->stored_items_reference,
            'name'          => $this->stored_items_name,
            'quantity_left' => $this->total_quantity,
            'type'          => $this->item_type,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
