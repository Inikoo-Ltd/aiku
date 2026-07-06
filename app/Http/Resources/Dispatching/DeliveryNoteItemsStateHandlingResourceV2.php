<?php

/*
 * Author Louis Perez
 * Created on 02-07-2026-15h-53m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Http\Resources\Dispatching;

use App\Http\Resources\Inventory\LocationOrgStocksForPickingActionsResource;
use App\Models\Dispatching\DeliveryNoteItem;
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
 * @property mixed $packed_in
 * @property mixed $warehouse_area_picking_position
 * @property mixed $warehouse_area_code
 * @property mixed $batch_code
 * @property mixed $batch_code_id
 * @property mixed $expiry_date
 * @property mixed $organisation_id
 * @property mixed $quantity_waiting_warehouse
 * @property mixed $quantity_waiting_crm
 * @property mixed $notes
 * @property mixed $shop_slug
 */
class DeliveryNoteItemsStateHandlingResourceV2 extends JsonResource
{
    public function toArray($request): array
    {
        $packedIn = $this->packed_in;
        if ($packedIn == null) {
            $packedIn = 1;
        }

        $requiredFactionalData =
            riseDivisor(
                divideWithRemainder(
                    findSmallestFactors($this->quantity_required)
                ),
                $this->packed_in
            );

        /** @var DeliveryNoteItem $deliveryNoteItem */
        $deliveryNoteItem = $this->resource;

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

        $pickingLocations = @json_decode($this->location_org_stocks) ?? [];
        $quantityToPick = max(0, $this->quantity_required - $this->quantity_picked - $this->quantity_not_picked - $this->quantity_waiting_warehouse - $this->quantity_waiting_crm);


        $isPicked = $quantityToPick == 0;
        $isPacked = $isPicked && $this->quantity_packed == $this->quantity_picked;

        // TODO: RAUL REVIEW RESOURCE
        // I don't think making jsonb would be a good idea.
        // I kept it as it is, but make it simpler from parent instead of using with, less expensive (Checked performance using EXPLAIN ANALYZE)
        $pickings = $deliveryNoteItem
            ->pickings()
            ->leftJoin('batch_codes', 'batch_codes.id', 'pickings.batch_code_id')
            ->select([
                'pickings.*',
                'batch_codes.id as batch_code_id',
                'batch_codes.code as batch_code',
                DB::raw("'{$this->org_stocks_batch_code_id}' as org_stocks_batch_code_id"),
                DB::raw("'{$this->org_stocks_batch_code}' as org_stocks_batch_code"),
                DB::raw("'{$this->org_stocks_batch_code_count}' as org_stocks_batch_code_count"),
                DB::raw("'{$packedIn}' as packed_in"),
            ])
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

        $packedInMessage = '';
        if ($packedIn == 1) {
            $packedInMessage = '('.__('Individually packed').')';
        } elseif ($packedIn > 1) {
            $packedInMessage = '('.__('Pack of').": $packedIn".")";
        }

        $quantityToPickFractional   = riseDivisor(divideWithRemainder(findSmallestFactors($quantityToPick)), $this->packed_in);
        $quantityToPickFractionalDS = $quantityToPickFractional;

        if (floor($quantityToPick) == $quantityToPick && $packedIn > 1) {
            $quantityToPickFractionalDS = [0, [$quantityToPick * $this->packed_in, $this->packed_in]];
        }

        return [
            'id'                             => $this->id,
            'is_picked'                      => $isPicked,
            'state'                          => $this->state,
            'state_icon'                     => $this->state->stateIcon()[$this->state->value],
            'quantity_required'              => $this->quantity_required,
            'quantity_to_pick'               => $quantityToPick,
            'quantity_to_pick_fractional'    => $quantityToPickFractional,
            'quantity_to_pick_fractional_ds' => $quantityToPickFractionalDS,
            'quantity_picked_fractional'     => $this->quantity_picked > 0 ? riseDivisor(divideWithRemainder(findSmallestFactors($quantityToPick)), $this->quantity_picked) : null,
            'quantity_picked'                => $this->quantity_picked,
            'quantity_not_picked'            => $this->quantity_not_picked,
            'quantity_packed'                => $this->quantity_packed,
            'quantity_dispatched'            => $this->quantity_dispatched,
            'quantity_waiting_warehouse'     => $this->quantity_waiting_warehouse,
            'quantity_waiting_crm'           => $this->quantity_waiting_crm,

            'org_stock_id'                   => $this->org_stock_id,
            'org_stock_code'                 => $this->org_stock_code,
            'org_stock_slug'                 => $this->org_stock_slug,
            'org_stock_name'                 => $this->org_stock_name,

            //=== PERFORMANCE
            // If vika only use this in a modal, remve this line, and ask vika to get the individual image by ajax when the modal is opened
            // TODO LOUIS PERFORMANCE WILL MAKE AN AJAX CALL FOR THIS, FOR NOW WILL LEFT NULL
            // 'org_stock_image_thumbnail'      => $deliveryNoteItem->orgStock?->tradeUnits->first()?->imageSources(64, 64),
            'org_stock_image_thumbnail'      => null,
            'locations'                      => LocationOrgStocksForPickingActionsResource::collection($pickingLocations),
            'pickings'                       => PickingResourceForDeliveryNoteItemsStateHandling::collection($pickings),
            // TODO: RAUL REVIEW RESOURCE
            // I don't think making jsonb would be a good idea.
            'packings'                       => PackingsResource::collection($deliveryNoteItem->packings),
            'warning'                        => $fullWarning,
            'is_handled'                     => $this->is_handled,
            'is_packed'                      => $isPacked,
            'quantity_required_fractional'   => $requiredFactionalData,
            'warehouse_area'                 => $warehouseArea,
            'batch_code'                     => $this->batch_code,
            'batch_code_id'                  => $this->batch_code_id,
            'expiry_date'                    => $this->expiry_date,
            'organisation_id'                => $this->organisation_id,
            'batch_codes_fetch_route'        => [
                'name'       => 'grp.json.org_stock.batch_codes.index',
                'parameters' => [
                    'organisation' => $this->organisation_id,
                    'orgStock'     => $this->org_stock_id,
                ],
            ],
            'packed_in_message'              => $packedInMessage,
            'notes'                          => $this->notes,
            'shop_slug'                      => $this->shop_slug,
            'un_numbers'                     => @json_decode($this->un_numbers) ?? null,
            'upsert_picking_route'           => [
                'name'       => 'grp.models.delivery_note_item.picking.upsert',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],
            'picking_route'                  => [
                'name'       => 'grp.models.delivery_note_item.picking.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],
            'picking_all_route'              => [
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
                    'organisation' => $this->organisation_slug
                ]
            ],
            'packers_list_route' => [
                'name'       => 'grp.json.employees.packers',
                'parameters' => [
                    'organisation' => $this->organisation_slug
                ]
            ],
        ];
    }
}
