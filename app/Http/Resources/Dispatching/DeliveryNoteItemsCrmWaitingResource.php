<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 16:22:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
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
 * @property mixed $org_stock_slug
 * @property mixed $packed_in
 * @property mixed $warehouse_area_picking_position
 * @property mixed $warehouse_area_code
 * @property mixed $batch_code
 * @property mixed $expiry_date
 * @property mixed $quantity_waiting_warehouse
 * @property mixed $quantity_waiting_crm
 * @property mixed $notes
 * @property mixed $shop_slug
 */
class DeliveryNoteItemsCrmWaitingResource extends JsonResource
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


        $quantityToPick = max(0, $this->quantity_required - $this->quantity_picked - $this->quantity_not_picked - $this->quantity_waiting_warehouse - $this->quantity_waiting_crm);


        $isPicked = $quantityToPick == 0;


        $quantityToPickFractional   = riseDivisor(divideWithRemainder(findSmallestFactors($quantityToPick)), $this->packed_in);
        $quantityToPickFractionalDS = $quantityToPickFractional;

        if (floor($quantityToPick) == $quantityToPick && $packedIn > 1) {
            $quantityToPickFractionalDS = [0, [$quantityToPick * $this->packed_in, $this->packed_in]];
        }

        return [
            'id'                             => $this->id,
            'is_picked'                      => $isPicked,
            'quantity_required'              => $this->quantity_required,
            'quantity_to_pick'               => $quantityToPick,
            'quantity_to_pick_fractional'    => $quantityToPickFractional,
            'quantity_to_pick_fractional_ds' => $quantityToPickFractionalDS,
            'quantity_picked_fractional'     => riseDivisor(divideWithRemainder(findSmallestFactors($quantityToPick)), $this->quantity_picked),
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
            'is_handled'                     => $this->is_handled,
            'quantity_required_fractional'   => $requiredFactionalData,
            'notes'                          => $this->notes,


        ];
    }
}
