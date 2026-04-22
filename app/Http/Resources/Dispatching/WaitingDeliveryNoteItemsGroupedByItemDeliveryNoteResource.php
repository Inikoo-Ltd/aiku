<?php

/*
 * Author: Vika Aqordi
 * Created on 22-04-2026-11h-28m
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
 * @property mixed $quantity_required
 * @property mixed $quantity_picked
 * @property mixed $quantity_not_picked
 * @property mixed $quantity_packed
 * @property mixed $quantity_dispatched
 * @property mixed $quantity_waiting_warehouse
 * @property mixed $quantity_waiting_crm
 * @property mixed $is_handled
 * @property mixed $notes
 */
class WaitingDeliveryNoteItemsGroupedByItemDeliveryNoteResource extends JsonResource
{
    public function toArray($request): array
    {
        $deliveryNoteItem = DeliveryNoteItem::find($this->id);
        $deliveryNote     = $deliveryNoteItem?->deliveryNote;
        $orgStock         = $deliveryNoteItem?->orgStock;
        $packedIn         = $orgStock?->packed_in ?? 1;

        $warehouseArea = DB::table('locations')
            ->leftJoin('warehouse_areas', 'warehouse_areas.id', '=', 'locations.warehouse_area_id')
            ->where('locations.id', $orgStock?->picking_location_id)
            ->selectRaw("concat_ws(' ', case when warehouse_areas.picking_position is not null then concat('Sort: ', warehouse_areas.picking_position) end, case when warehouse_areas.code is not null then concat('Area: ', warehouse_areas.code) end)")
            ->value('concat_ws');
        $warehouseArea = $warehouseArea ?: __('No Area');

        $packedInMessage = '';
        if ($packedIn === 1) {
            $packedInMessage = '('.__('Individually packed').')';
        } elseif ($packedIn > 1) {
            $packedInMessage = '('.__('Pack of').": $packedIn".')';
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

        return [
            'id'                                => $this->id,
            'state'                             => $this->state,
            'state_icon'                        => $this->state->stateIcon()[$this->state->value],

            'org_stock_id'                      => $orgStock?->id,
            'org_stock_code'                    => $orgStock?->code,
            'org_stock_name'                    => $orgStock?->name,
            'org_stock_slug'                    => $orgStock?->slug,
            'org_stock_image_thumbnail'         => $orgStock?->tradeUnits->first()?->imageSources(64, 64),
            'packed_in'                         => $packedIn,
            'packed_in_message'                 => $packedInMessage,
            'warehouse_area'                    => $warehouseArea,

            'delivery_note_slug'                => $deliveryNote?->slug,
            'delivery_note_reference'           => $deliveryNote?->reference,
            'delivery_note_is_premium_dispatch' => $deliveryNote?->is_premium_dispatch,
            'delivery_note_has_extra_packing'   => $deliveryNote?->has_extra_packing,
            'delivery_note_customer_notes'      => $deliveryNote?->customer_notes,
            'delivery_note_public_notes'        => $deliveryNote?->public_notes,
            'delivery_note_internal_notes'      => $deliveryNote?->internal_notes,
            'delivery_note_shipping_notes'      => $deliveryNote?->shipping_notes,

            'quantity_required'                 => $this->quantity_required,
            'quantity_picked'                   => $this->quantity_picked,
            'quantity_not_picked'               => $this->quantity_not_picked,
            'quantity_packed'                   => $this->quantity_packed,
            'quantity_dispatched'               => $this->quantity_dispatched,
            'quantity_waiting_warehouse'        => $this->quantity_waiting_warehouse,
            'quantity_waiting_crm'              => $this->quantity_waiting_crm,

            'is_handled'                        => $this->is_handled,
            'notes'                             => $this->notes,

            'locations'                         => $pickingLocations->isNotEmpty() ? LocationOrgStocksForPickingActionsResource::collection($pickingLocations) : [],
            'pickings'                          => PickingResource::collection($pickings),
            'packings'                          => $deliveryNoteItem?->packings ? PackingsResource::collection($deliveryNoteItem->packings) : [],

            'upsert_picking_route'              => [
                'name'       => 'grp.models.delivery_note_item.picking.upsert_from_waiting_warehouse',
                'parameters' => ['deliveryNoteItem' => $this->id],
                'method'     => 'post',
            ],
            'picking_all_route'                 => [
                'name'       => 'grp.models.delivery_note_item.picking_all_from_waiting_warehouse.store',
                'parameters' => ['deliveryNoteItem' => $this->id],
                'method'     => 'post',
            ],
        ];
    }
}
