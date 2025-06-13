<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $not_picked_reason
 * @property mixed $not_picked_note
 * @property mixed $quantity
 * @property mixed $engine
 * @property mixed $picker
 * @property mixed $type
 * @property mixed $location
 */
class PickingsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'not_picked_reason'   => $this->not_picked_reason,
            'not_picked_note'     => $this->not_picked_note,
            'quantity_picked'     => $this->quantity,
            'engine'              => $this->engine,
            'picker_name'         => $this->picker->contact_name,
            'type'                => $this->type,
            'location_code'       => $this->location?->code,
            'location_slug'       => $this->location?->slug,
            'location_id'         => $this->location?->id,
            'update_route'        => [
                'name'  => 'grp.models.picking.update',
                'parameters' => [
                    'picking' => $this->id
                ],
                'method' => 'patch'
            ],
            'undo_picking_route'        => [
                'name'  => 'grp.models.picking.delete',
                'parameters' => [
                    'picking' => $this->id
                ],
                'method' => 'delete'
            ],
        ];
    }
}
