<?php

/*
 * author Louis Perez
 * created on 17-03-2026-12h-33m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNoteItem;
use Lorisleiva\Actions\ActionRequest;

class FetchSingleDeliveryNoteItem extends OrgAction
{
    public function handle(DeliveryNoteItem $deliveryNoteItem): DeliveryNoteItem
    {
        return $deliveryNoteItem;
    }

    public function jsonResponse(DeliveryNoteItem $deliveryNoteItem): array
    {
        $requiredFactionalData = riseDivisor(
            divideWithRemainder(
                findSmallestFactors(
                    $deliveryNoteItem->quantity_required
                )
            ),
            $deliveryNoteItem->orgStock->packed_in
        );

        $packedIn = $deliveryNoteItem->orgStock->packed_in;
        if ($packedIn == null) {
            $packedIn = 1;
        }


        $quantityDispatched = $deliveryNoteItem->quantity_dispatched;
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
            'id'                             => $deliveryNoteItem->id,
            'state'                          => $deliveryNoteItem->state,
            'state_icon'                     => $deliveryNoteItem->state->stateIcon()[$deliveryNoteItem->state->value],
            'quantity_required'              => $deliveryNoteItem->quantity_required,
            'quantity_required_fractional'   => $requiredFactionalData,
            'quantity_dispatched'            => $deliveryNoteItem->quantity_dispatched,
            'quantity_dispatched_fractional' => riseDivisor(divideWithRemainder(findSmallestFactors($quantityDispatched)), $packedIn),
            'quantity_picked'                => $deliveryNoteItem->quantity_picked,
            'quantity_picked_fractional'     => riseDivisor(divideWithRemainder(findSmallestFactors($deliveryNoteItem->quantity_picked ?? 0)), $packedIn),
            'quantity_packed'                => $deliveryNoteItem->quantity_packed,
            'quantity_packed_fractional'     => riseDivisor(divideWithRemainder(findSmallestFactors($deliveryNoteItem->quantity_packed ?? 0)), $packedIn),
            'quantity_not_picked'            => $deliveryNoteItem->quantity_not_picked,
            'org_stock_code'                 => $deliveryNoteItem->orgStock->code,
            'org_stock_name'                 => $deliveryNoteItem->orgStock->name,
            'org_stock_slug'                 => $deliveryNoteItem->orgStock->slug,
            'org_stock_id'                   => $deliveryNoteItem->orgStock->id,
            'batch_code'                     => $deliveryNoteItem->batch_code,
            'expiry_date'                    => $deliveryNoteItem->expiry_date,
            'packed_in_message'              => $packedInMessage,
            'is_done_packing'                => $deliveryNoteItem->packings()->exists(),
        ];
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem);
    }
}
