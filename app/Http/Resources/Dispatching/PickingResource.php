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
 * @property mixed $orgStock
 * @property mixed $batch_code_id
 * @property mixed $org_stock_id
 * @property mixed $organisation_id
 * @property mixed $batchCode
 */
class PickingResource extends JsonResource
{
    public function toArray($request): array
    {
        //todo: do this in a left join better
        $pickerName = __('Unknown');
        if ($this->picker) {
            $pickerName = $this->picker->contact_name;
        }

        return [
            'id'                         => $this->id,
            'not_picked_reason'          => $this->not_picked_reason,
            'not_picked_note'            => $this->not_picked_note,
            'quantity_picked'            => (float)$this->quantity,
            'quantity_picked_fractional' => riseDivisor(
                divideWithRemainder(findSmallestFactors($this->quantity)),
                $this->orgStock?->packed_in
            ),
            'engine'                     => $this->engine,
            'picker_name'                => $pickerName,
            'type'                       => $this->type,
            'location_code'              => $this->location?->code,
            'location_slug'              => $this->location?->slug,
            'location_id'                => $this->location?->id,
            'show_batch_code_ui'         => $this->orgStock?->current_batch_codes > 0,
            'batch_code_id'              => $this->batch_code_id ?? $this->orgStock?->mainBatchCode?->id,
            'batch_code'                 => $this->batchCode?->code ?? $this->orgStock?->mainBatchCode?->code,
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
