<?php

/*
 * author Louis Perez
 * created on 05-05-2026-13h-40m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Procurement;

use App\Http\Resources\HasSelfCall;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\GoodsIn\Sowing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ReturnDeliveryNoteItemsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray(Request $request)
    {
        /** @var ReturnDeliveryNoteItem $returnDeliveryNoteItem */
        $returnDeliveryNoteItem = $this;

        $returnLocation = DB::table('location_org_stocks')
            ->leftJoin('locations', 'location_org_stocks.location_id', '=', 'locations.id')
            ->leftjoin('warehouse_areas', 'warehouse_areas.id', '=', 'locations.warehouse_area_id')
            ->where('org_stock_id', $this->org_stock_id)
            ->select([
                'location_org_stocks.id',
                'location_org_stocks.quantity',
                'location_org_stocks.type',
                'locations.id as location_id',
                'locations.code as location_code',
                'locations.slug as location_slug',
            ])
            ->selectRaw('\''.$returnDeliveryNoteItem->packed_in.'\' as org_stock_packed_in')
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

        $warehouseArea = '';
        if ($this->warehouse_area_picking_position) {
            $warehouseArea = __('Sort:').': '.$this->warehouse_area_picking_position.' ';
        }

        if ($this->warehouse_area_code) {
            $warehouseArea .= __('Area').': '.$this->warehouse_area_code;
        }
        if ($warehouseArea == '') {
            $warehouseArea = __('No Area');
        }

        $sowings = Sowing::where('return_item_id', $this->id)->with('location')->get();

        $maxAvailableQty = $returnDeliveryNoteItem->total_expected_qty - (
            $returnDeliveryNoteItem->total_item_damaged +
            $returnDeliveryNoteItem->total_item_not_returned +
            $returnDeliveryNoteItem->total_item_returned
        );

        $maxAvailableQtyFractional = riseDivisor(
            divideWithRemainder(
                findSmallestFactors(
                    $maxAvailableQty
                )
            ),
            $returnDeliveryNoteItem->packed_in
        );

        $maxAvailableQtyFractionalDS = $maxAvailableQtyFractional;

        if (floor($maxAvailableQty) == $maxAvailableQtyFractional &&  $returnDeliveryNoteItem->packed_in > 1) {
            $maxAvailableQtyFractionalDS = [0, [$maxAvailableQtyFractional * $this->packed_in, $this->packed_in]];
        }

        return [
            'id'                                    => $returnDeliveryNoteItem->id,
            'state'                                 => $returnDeliveryNoteItem->state,
            'state_icon'                            => $this->state->stateIcon(),
            'expected_quantity'                     => $returnDeliveryNoteItem->total_expected_qty,
            'expected_quantity_fractional'          =>  riseDivisor(
                divideWithRemainder(
                    findSmallestFactors(
                        $returnDeliveryNoteItem->total_expected_qty
                    )
                ),
                $returnDeliveryNoteItem->packed_in
            ),
            'quantity_to_sow'                       => $maxAvailableQty,
            'quantity_to_sow_fractional'            => $maxAvailableQtyFractional,
            'quantity_to_sow_fractional_ds'         => $maxAvailableQtyFractionalDS,


            'total_item_not_returned'               => $returnDeliveryNoteItem->total_item_not_returned,
            'total_item_not_returned_fractional'    =>  riseDivisor(
                divideWithRemainder(
                    findSmallestFactors(
                        $returnDeliveryNoteItem->total_item_not_returned
                    )
                ),
                $returnDeliveryNoteItem->packed_in
            ),
            'upsert_not_returned_route'              => [
                'name'       => 'grp.models.return_delivery_note_item.upsert_not_returned',
                'parameters' => ['returnDeliveryNoteItem' => $this->id],
                'method'     => 'patch',
            ],
            'set_all_not_returned_route'              => [
                'name'       => 'grp.models.return_delivery_note_item.set_all_not_returned',
                'parameters' => ['returnDeliveryNoteItem' => $this->id],
                'method'     => 'patch',
            ],


            'total_item_damaged'                    => $returnDeliveryNoteItem->total_item_damaged,
            'total_item_damaged_fractional'         =>  riseDivisor(
                divideWithRemainder(
                    findSmallestFactors(
                        $returnDeliveryNoteItem->total_item_damaged
                    )
                ),
                $returnDeliveryNoteItem->packed_in
            ),
            'upsert_damaged_route'              => [
                'name'       => 'grp.models.return_delivery_note_item.upsert_damaged',
                'parameters' => ['returnDeliveryNoteItem' => $this->id],
                'method'     => 'patch',
            ],
            'set_all_damaged_route'              => [
                'name'       => 'grp.models.return_delivery_note_item.set_all_damaged',
                'parameters' => ['returnDeliveryNoteItem' => $this->id],
                'method'     => 'patch',
            ],


            'total_item_returned'                   => $returnDeliveryNoteItem->total_item_returned,
            'total_item_returned_fractional'        =>  riseDivisor(
                divideWithRemainder(
                    findSmallestFactors(
                        $returnDeliveryNoteItem->total_item_returned
                    )
                ),
                $returnDeliveryNoteItem->packed_in
            ),
            'upsert_returned_route'              => [
                'name'       => 'grp.models.return_delivery_note_item.upsert_returned',
                'parameters' => ['returnDeliveryNoteItem' => $this->id],
                'method'     => 'patch',
            ],
            'set_all_returned_route'              => [
                'name'       => 'grp.models.return_delivery_note_item.set_all_returned',
                'parameters' => ['returnDeliveryNoteItem' => $this->id],
                'method'     => 'patch',
            ],


            'org_stock_id'                          => $returnDeliveryNoteItem->org_stock_id,
            'org_stock_code'                        => $returnDeliveryNoteItem->org_stock_code,
            'org_stock_name'                        => $returnDeliveryNoteItem->org_stock_name,
            'org_stock_slug'                        => $returnDeliveryNoteItem->org_stock_slug,
            'packed_in'                             => $returnDeliveryNoteItem->packed_in,
            'locations'                             => $returnLocation,
            'warehouse_area'                        => $warehouseArea,

            'sowings'                               => SowingResource::collection($sowings),
            'has_available_qty'                     => (bool) $maxAvailableQty > 0,
        ];
    }
}
