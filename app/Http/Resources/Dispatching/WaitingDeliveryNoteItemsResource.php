<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $delivery_note_id
 * @property mixed $delivery_note_slug
 * @property mixed $delivery_note_reference
 * @property mixed $org_stock_id
 * @property mixed $org_stock_code
 * @property mixed $org_stock_name
 * @property mixed $quantity_required
 * @property mixed $quantity_picked
 * @property mixed $quantity_waiting
 * @property mixed $picking_position
 * @property mixed $warehouse_area_code
 */
class WaitingDeliveryNoteItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'delivery_note_id'        => $this->delivery_note_id,
            'delivery_note_slug'      => $this->delivery_note_slug,
            'delivery_note_reference' => $this->delivery_note_reference,
            'org_stock_id'            => $this->org_stock_id,
            'org_stock_code'          => $this->org_stock_code,
            'org_stock_name'          => $this->org_stock_name,
            'quantity_waiting'        => $this->quantity_waiting,
            'picking_position'        => $this->picking_position,
            'warehouse_area_code'     => $this->warehouse_area_code,
        ];
    }
}
