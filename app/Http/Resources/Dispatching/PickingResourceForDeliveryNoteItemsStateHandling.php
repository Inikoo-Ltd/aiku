<?php

/*
 * Author Louis Perez
 * Created on 02-07-2026-15h-31m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
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
 * @property mixed $orgStock
 * @property mixed $batch_code_id
 * @property mixed $org_stock_id
 * @property mixed $organisation_id
 * @property mixed $batchCode
 */
class PickingResourceForDeliveryNoteItemsStateHandling extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                         => $this->id,
            'not_picked_reason'          => $this->not_picked_reason,
            'not_picked_note'            => $this->not_picked_note,
            'quantity_picked'            => (float)$this->quantity,
            'quantity_picked_fractional' => riseDivisor(
                divideWithRemainder(findSmallestFactors($this->quantity)),
                $this->packed_in
            ),
            'engine'                     => $this->engine,
            'type'                       => $this->type,
            'location_code'              => $this->location?->code,
            'location_slug'              => $this->location?->slug,
            'location_id'                => $this->location?->id,
            'show_batch_code_ui'         => $this->org_stocks_batch_code_count > 0,
            'batch_code_id'              => $this->batch_code_id ?? $this->org_stocks_batch_code_id,
            'batch_code'                 => $this->batch_code ?? $this->org_stocks_batch_code,
            'org_stock_id'               => $this->org_stock_id,
            'organisation_id'            => $this->organisation_id,
            'batch_codes_fetch_route'    => [
                'name'       => 'grp.json.org_stock.batch_codes.index',
                'parameters' => [
                    'organisation' => $this->organisation_id,
                    'orgStock'     => $this->org_stock_id,
                ],
            ],

            'update_route'       => [
                'name'       => 'grp.models.picking.update',
                'parameters' => [
                    'picking' => $this->id
                ],
                'method'     => 'patch'
            ],
            'split_route'        => [
                'name'       => 'grp.models.picking.split',
                'parameters' => [
                    'picking' => $this->id
                ],
                'method'     => 'post'
            ],
            'undo_picking_route' => [
                'name'       => 'grp.models.picking.delete',
                'parameters' => [
                    'picking' => $this->id
                ],
                'method'     => 'delete'
            ],
        ];
    }
}
