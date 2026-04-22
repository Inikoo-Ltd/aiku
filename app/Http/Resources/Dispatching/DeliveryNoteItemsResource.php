<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

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
 * @property mixed $packed_in
 * @property mixed $org_stock_slug
 * @property mixed $batch_code
 * @property mixed $expiry_date
 * @property mixed $quantity_waiting_warehouse
 * @property mixed $quantity_waiting_crm
 */
class DeliveryNoteItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        $requiredFactionalData = riseDivisor(
            divideWithRemainder(
                findSmallestFactors(
                    $this->quantity_required
                )
            ),
            $this->packed_in
        );


        $packedIn = $this->packed_in;
        if ($packedIn == null) {
            $packedIn = 1;
        }


        $quantityDispatched = $this->quantity_dispatched;
        if ($quantityDispatched == null) {
            $quantityDispatched = 0;
        }

        $packedInMessage = '';
        if ($packedIn == 1) {
            $packedInMessage = '('.__('Individually packed').')';
        } elseif ($packedIn > 1) {
            $packedInMessage = '('.__('Pack of').": $packedIn".")";
        }

        return [
            'id'                             => $this->id,
            'state'                          => $this->state,
            'state_icon'                     => $this->state->stateIcon()[$this->state->value],
            'quantity_required'              => $this->quantity_required,
            'quantity_required_fractional'   => $requiredFactionalData,
            'quantity_dispatched'            => $this->quantity_dispatched,
            'quantity_dispatched_fractional' => riseDivisor(divideWithRemainder(findSmallestFactors($quantityDispatched)), $packedIn),
            'quantity_picked'                => $this->quantity_picked,
            'quantity_picked_fractional'     => riseDivisor(divideWithRemainder(findSmallestFactors($this->quantity_picked ?? 0)), $packedIn),
            'quantity_packed'                => $this->quantity_packed,
            'quantity_packed_fractional'     => riseDivisor(divideWithRemainder(findSmallestFactors($this->quantity_packed ?? 0)), $packedIn),
            'quantity_not_picked'            => $this->quantity_not_picked,
            'org_stock_code'                 => $this->org_stock_code,
            'org_stock_name'                 => $this->org_stock_name,
            'org_stock_slug'                 => $this->org_stock_slug,
            'org_stock_id'                   => $this->org_stock_id,
            'batch_code'                     => $this->batch_code,
            'expiry_date'                    => $this->expiry_date,
            'packed_in_message'              => $packedInMessage,
            'is_done_packing'                => (bool)$this->packing_id,
            'is_picked'                      => $this->is_picked,
            'is_packed'                      => $this->is_packed,
            'quantity_waiting_warehouse'     => $this->quantity_waiting_warehouse,
            'quantity_waiting_crm'           => $this->quantity_waiting_crm,


            'picking_locations' => $this->pickings
                ->where('type', '!=', \App\Enums\Dispatching\Picking\PickingTypeEnum::NOT_PICK)
                ->where('quantity', '!=', 0)
                ->groupBy('location_id')
                ->map(function ($pickingGroup) {
                    $firstPicking = $pickingGroup->first();
                    $location     = $firstPicking->location;

                    return [
                        'id'             => ('loc_'.($location ? $location->id : 'none')),
                        'quantity'       => $pickingGroup->sum('quantity'),
                        'location_slug'  => $location ? $location->slug : null,
                        'location_code'  => $location ? $location->code : null,
                        'warehouse_slug' => $location ? $location->warehouse->slug : null,
                        'warehouse_code' => $location ? $location->warehouse->code : null,
                    ];
                })->values()->toArray(),
            'not_picking_route' => [
                'name'       => 'grp.models.delivery_note_item.not_picking.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],
        ];
    }
}
