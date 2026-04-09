<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
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
 * @property mixed $delivery_note_slug
 * @property mixed $delivery_note_reference
 * @property mixed $delivery_note_state
 * @property mixed $delivery_note_customer_notes
 * @property mixed $delivery_note_public_notes
 * @property mixed $delivery_note_internal_notes
 * @property mixed $delivery_note_shipping_notes
 * @property mixed $delivery_note_is_premium_dispatch
 * @property mixed $delivery_note_has_extra_packing
 * @property mixed $shop_type
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
 * @property mixed $batch_code
 * @property mixed $expiry_date
 * @property mixed $picking_position
 * @property mixed $warehouse_area_code
 * @property mixed $warehouse_area_picking_position
 */
class WaitingDeliveryNoteItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        $packedIn = $this->packed_in ?? 1;

        $quantityToPick = max(0, $this->quantity_required - $this->quantity_picked - ($this->quantity_not_picked ?? 0) - ($this->quantity_waiting_warehouse ?? 0) - ($this->quantity_waiting_crm ?? 0));

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

        $quantityToPickFractional   = riseDivisor(divideWithRemainder(findSmallestFactors($quantityToPick)), $packedIn);
        $quantityToPickFractionalDS = $quantityToPickFractional;
        if (floor($quantityToPick) == $quantityToPick && $packedIn > 1) {
            $quantityToPickFractionalDS = [0, [$quantityToPick * $packedIn, $packedIn]];
        }

        $deliveryNoteItem = DeliveryNoteItem::find($this->id);

        return [
            'id'                                => $this->id,
            'state'                             => $this->state,
            'state_icon'                        => $this->state->stateIcon()[$this->state->value],
            'delivery_note_slug'                => $this->delivery_note_slug,
            'delivery_note_reference'           => $this->delivery_note_reference,
            'delivery_note_state'               => $this->delivery_note_state,
            'delivery_note_customer_notes'      => $this->delivery_note_customer_notes,
            'delivery_note_public_notes'        => $this->delivery_note_public_notes,
            'delivery_note_internal_notes'      => $this->delivery_note_internal_notes,
            'delivery_note_shipping_notes'      => $this->delivery_note_shipping_notes,
            'delivery_note_is_premium_dispatch' => $this->delivery_note_is_premium_dispatch,
            'delivery_note_has_extra_packing'   => $this->delivery_note_has_extra_packing,
            'delivery_note_shop_type'           => $this->shop_type,
            'org_stock_id'                      => $this->org_stock_id,
            'org_stock_code'                    => $this->org_stock_code,
            'org_stock_name'                    => $this->org_stock_name,
            'org_stock_slug'                    => $this->org_stock_slug,
            'packed_in'                         => $packedIn,
            'packed_in_message'                 => $packedInMessage,
            'quantity_required'                 => $this->quantity_required,
            'quantity_to_pick'                  => $quantityToPick,
            'quantity_to_pick_fractional'       => $quantityToPickFractional,
            'quantity_to_pick_fractional_ds'    => $quantityToPickFractionalDS,
            'quantity_picked'                   => $this->quantity_picked,
            'quantity_not_picked'               => $this->quantity_not_picked,
            'is_handled'                        => $this->is_handled,
            'picking_position'                  => $this->picking_position,
            'warehouse_area'                    => $warehouseArea,
            'locations'                         => $pickingLocations->isNotEmpty() ? LocationOrgStocksForPickingActionsResource::collection($pickingLocations) : [],
            'pickings'                          => PickingResource::collection($pickings),
            'packings'                          => $deliveryNoteItem?->packings ? PackingsResource::collection($deliveryNoteItem->packings) : [],

            'upsert_picking_route' => [
                'name'       => 'grp.models.delivery_note_item.picking.upsert',
                'parameters' => ['deliveryNoteItem' => $this->id],
                'method'     => 'post',
            ],
            'picking_all_route'    => [
                'name'       => 'grp.models.delivery_note_item.picking_all.store',
                'parameters' => ['deliveryNoteItem' => $this->id],
                'method'     => 'post',
            ],
            'not_picking_route'    => [
                'name'       => 'grp.models.delivery_note_item.not_picking.store',
                'parameters' => ['deliveryNoteItem' => $this->id],
                'method'     => 'post',
            ],
        ];
    }
}
