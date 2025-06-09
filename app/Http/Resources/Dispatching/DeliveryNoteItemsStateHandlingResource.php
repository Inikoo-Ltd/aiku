<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 14:00:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Http\Resources\Inventory\LocationOrgStocksForPickingActionsResource;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * @property mixed $org_stock_id
 * @property mixed $id
 * @property mixed $state
 * @property mixed $quantity_required
 * @property mixed $quantity_picked
 * @property mixed $org_stock_code
 * @property mixed $org_stock_name
 * @property mixed $is_handled
 * @property mixed $quantity_packed
 * @property mixed $quantity_not_picked
 * @property mixed $quantity_dispatched
 * @property mixed $org_stock_slug
 */
class DeliveryNoteItemsStateHandlingResource extends JsonResource
{
    public function toArray($request): array
    {
        $deliveryNoteItem = DeliveryNoteItem::find($this->id);
        $fullWarning      = [
            'disabled' => false,
            'message'  => ''
        ];
        if ($this->quantity_picked == $this->quantity_required) {
            $fullWarning = [
                'disabled' => true,
                'message'  => __('The required quantity has already been fully picked.')
            ];
        }
        $pickingLocations = DB::table('location_org_stocks')
            ->leftJoin('locations', 'location_org_stocks.location_id', '=', 'locations.id')
            ->where('org_stock_id', $this->org_stock_id)
            ->select([
                'location_org_stocks.id',
                'location_org_stocks.quantity',
                'location_org_stocks.type',
                'locations.id as location_id',
                'locations.code as location_code',
                'locations.slug as location_slug',
            ])
            ->selectRaw(
                '(
        SELECT concat(sum(quantity),\';\',string_agg(id::char,\',\')) FROM pickings
        WHERE pickings.location_id = location_org_stocks.location_id
        AND pickings.org_stock_id = location_org_stocks.org_stock_id
        AND pickings.type = ? AND pickings.delivery_note_item_id = ?
    ) as pickings_data',
                ['pick', $this->id]
            )
            ->orderBy('picking_priority')->get();



        $quantityToPick = max(0, $this->quantity_required - $this->quantity_picked);


        $isPicked = $quantityToPick == 0;
        $isPacked = $isPicked && $this->quantity_packed == $this->quantity_picked;



        $pickings = Picking::where('delivery_note_item_id', $this->id)
            ->where('type', PickingTypeEnum::PICK)
            ->get();


        return [
            'id'        => $this->id,
            'is_picked' => $isPicked,

            'state'               => $this->state,
            'state_icon'          => $this->state->stateIcon()[$this->state->value],
            'quantity_required'   => $this->quantity_required,
            'quantity_to_pick'    => $quantityToPick,
            'quantity_picked'     => $this->quantity_picked,
            'quantity_not_picked' => (int) $this->quantity_not_picked,
            'quantity_packed'     => $this->quantity_packed,
            'quantity_dispatched' => $this->quantity_dispatched,
            'org_stock_code'      => $this->org_stock_code,
            'org_stock_slug'      => $this->org_stock_slug,
            'org_stock_name'      => $this->org_stock_name,
            'locations'           => $pickingLocations->isNotEmpty() ? LocationOrgStocksForPickingActionsResource::collection($pickingLocations) : [],
            'pickings'            => PickingsResource::collection($pickings),
            // 'pickings'=>$pickingsA,

            'packings'           => $deliveryNoteItem->packings ? PackingsResource::collection($deliveryNoteItem->packings) : [],
            'warning'            => $fullWarning,
            'is_handled'         => $this->is_handled,
            'is_packed'          => $isPacked,

            'upsert_picking_route'      => [
                'name'       => 'grp.models.delivery_note_item.picking.upsert',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],

            'picking_route'      => [
                'name'       => 'grp.models.delivery_note_item.picking.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],
            'picking_all_route'  => [
                'name'       => 'grp.models.delivery_note_item.picking_all.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],
            'not_picking_route'  => [
                'name'       => 'grp.models.delivery_note_item.not_picking.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],
            'packing_route'      => [
                'name'       => 'grp.models.delivery_note_item.packing.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],
            'pickers_list_route' => [
                'name'       => 'grp.json.employees.picker_users',
                'parameters' => [
                    'organisation' => $deliveryNoteItem->organisation->slug
                ]
            ],
            'packers_list_route' => [
                'name'       => 'grp.json.employees.packers',
                'parameters' => [
                    'organisation' => $deliveryNoteItem->organisation->slug
                ]
            ],
        ];
    }
}
