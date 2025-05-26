<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Http\Resources\Inventory\LocationOrgStocksResource;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\OrgStock;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        $orgStock = OrgStock::find($this->org_stock_id);
        $deliveryNoteItem = DeliveryNoteItem::find($this->id);
        $fullWarning = [
            'disabled' => false,
            'message' => ''
        ];
        if ($deliveryNoteItem->quantity_picked == $deliveryNoteItem->quantity_required) {
            $fullWarning = [
                'disabled' => true,
                'message' => __('The required quantity has already been fully picked.')
            ];
        }
        return [
            'id'                  => $this->id,
            'state'               => $this->state,
            'state_icon'          => $this->state->stateIcon()[$this->state->value],
            'quantity_required'   => intVal($this->quantity_required),
            'quantity_to_pick'    => intVal($this->quantity_required) - intVal($this->quantity_picked),
            'quantity_picked'     => intVal($this->quantity_picked),
            'quantity_not_picked' => intVal($this->quantity_not_picked),
            'quantity_packed'     => intVal($this->quantity_packed),
            'quantity_dispatched' => intVal($this->quantity_dispatched),
            'org_stock_code'      => $this->org_stock_code,
            'org_stock_name'      => $this->org_stock_name,
            'locations'           => $orgStock->locationOrgstocks ? LocationOrgStocksResource::collection($orgStock->locationOrgstocks) : [],
            'pickings'            => $deliveryNoteItem->pickings ? PickingsResource::collection($deliveryNoteItem->pickings) : [],
            'packings'            => $deliveryNoteItem->packings ? PackingsResource::collection($deliveryNoteItem->packings) : [],
            'warning'             => $fullWarning,
            'is_completed'        => $this->is_completed,
            'picking_route'       => [
                'name' => 'grp.models.delivery-note-item.picking.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method' => 'post'
            ],
            'not_picking_route'       => [
                'name' => 'grp.models.delivery-note-item.not-picking.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method' => 'post'
            ],
            'pickers_list_route'   => [
                'name'       => 'grp.json.employees.picker_users',
                'parameters' => [
                    'organisation' => $deliveryNoteItem->organisation->slug
                ]
            ],
            'packers_list_route'   => [
                'name'       => 'grp.json.employees.packers',
                'parameters' => [
                    'organisation' => $deliveryNoteItem->organisation->slug
                ]
            ],
        ];
    }
}
