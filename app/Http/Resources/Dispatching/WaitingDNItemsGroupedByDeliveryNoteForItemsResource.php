<?php

/*
 * Author: Vika Aqordi
 * Created on 20-04-2026-15h-35m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Http\Resources\Dispatching;

use App\Http\Resources\Inventory\LocationOrgStocksForPickingActionsResource;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * @property mixed $id
 * @property mixed $state
 * @property mixed $org_stock_id
 * @property mixed $org_stock_code
 * @property mixed $org_stock_name
 * @property mixed $org_stock_slug
 * @property mixed $packed_in
 * @property mixed $quantity_required
 * @property mixed $quantity_picked
 * @property mixed $quantity_not_picked
 * @property mixed $quantity_packed
 * @property mixed $quantity_dispatched
 * @property mixed $quantity_waiting_warehouse
 * @property mixed $quantity_waiting_crm
 * @property mixed $is_handled
 * @property mixed $notes
 * @property mixed $picking_position
 * @property mixed $warehouse_area_code
 * @property mixed $warehouse_area_picking_position
 */
class WaitingDNItemsGroupedByDeliveryNoteForItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        $packedIn = $this->packed_in ?? 1;

        $deliveryNoteItem = DeliveryNoteItem::find($this->id);

        // $waitingWarehouseQuantity = $deliveryNoteItem->quantity_required
        //     - $deliveryNoteItem->quantity_waiting_warehouse
        //     - $deliveryNoteItem->quantity_waiting_crm
        //     - $deliveryNoteItem->quantity_not_picked;

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
            ->selectRaw('\''.$packedIn.'\' as org_stock_packed_in')
            ->selectRaw(
                '(
        SELECT concat(sum(quantity),\';\',string_agg(id::char,\',\')) FROM pickings
        WHERE pickings.location_id = location_org_stocks.location_id
        AND pickings.org_stock_id = location_org_stocks.org_stock_id
        AND pickings.type = ? AND pickings.delivery_note_item_id = ?
    ) as pickings_data',
                ['pick', $this->id]
            )
            ->orderBy('picking_priority')
            ->get();

        $pickings = Picking::where('delivery_note_item_id', $this->id)->get();

        $warehouseArea = '';
        if ($this->warehouse_area_picking_position) {
            $warehouseArea = __('Sort:').': '.$this->warehouse_area_picking_position.' ';
        }
        if ($this->warehouse_area_code) {
            $warehouseArea .= __('Area').': '.$this->warehouse_area_code;
        }
        if ($warehouseArea === '') {
            $warehouseArea = __('No Area');
        }

        $packedInMessage = '';
        if ($packedIn == 1) {
            $packedInMessage = '('.__('Individually packed').')';
        } elseif ($packedIn > 1) {
            $packedInMessage = '('.__('Pack of').": $packedIn".')';
        }

        return [
            // 'waiting_warehouse_quantity' => $waitingWarehouseQuantity,
            'id'                         => $this->id,
            'state'                      => $this->state,
            'state_icon'                 => $this->state->stateIcon()[$this->state->value],
            'org_stock_id'               => $this->org_stock_id,
            'org_stock_code'             => $this->org_stock_code,
            'org_stock_name'             => $this->org_stock_name,
            'org_stock_slug'             => $this->org_stock_slug,
            'org_stock_image_thumbnail'  => $deliveryNoteItem->orgStock?->tradeUnits->first()?->imageSources(64, 64),
            'packed_in'                  => $packedIn,
            'packed_in_message'          => $packedInMessage,

            'quantity_required'          => $this->quantity_required,
            'quantity_picked'            => $this->quantity_picked,
            'quantity_not_picked'        => $this->quantity_not_picked,
            'quantity_packed'            => $this->quantity_packed,
            'quantity_dispatched'        => $this->quantity_dispatched,
            'quantity_waiting_warehouse' => $this->quantity_waiting_warehouse,  // TODO: RAUL -- wrong quantity if multiple pickings location (case in page Index Waiting Warehouse Group)
            'quantity_waiting_crm'       => $this->quantity_waiting_crm,

            'is_handled'                 => $this->is_handled,
            'notes'                      => $this->notes,
            'picking_position'           => $this->picking_position,
            'warehouse_area'             => $warehouseArea,
            'locations'                  => $pickingLocations->isNotEmpty() ? LocationOrgStocksForPickingActionsResource::collection($pickingLocations) : [],
            'pickings'                   => PickingResource::collection($pickings),
            'packings'                   => $deliveryNoteItem?->packings ? PackingsResource::collection($deliveryNoteItem->packings) : [],
            'upsert_picking_route'       => [
                'name'       => 'grp.models.delivery_note_item.picking.upsert_from_waiting_warehouse',
                'parameters' => ['deliveryNoteItem' => $this->id],
                'method'     => 'post',
            ],
            'picking_all_route'          => [
                'name'       => 'grp.models.delivery_note_item.picking_all_from_waiting_warehouse.store',
                'parameters' => ['deliveryNoteItem' => $this->id],
                'method'     => 'post',
            ],
        ];
    }
}
