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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnDeliveryNoteItemsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray(Request $request)
    {
        /** @var ReturnDeliveryNoteItem $returnDeliveryNoteItem */
        $returnDeliveryNoteItem = $this;

        return [
            'id'                                    => $returnDeliveryNoteItem->id,
            'state'                                 => $returnDeliveryNoteItem->return_state,
            'state_icon'                            => $this->return_state->stateIcon(),
            'expected_quantity'                     => $returnDeliveryNoteItem->expected_quantity,
            'expected_quantity_fractional'          =>  riseDivisor(
                divideWithRemainder(
                    findSmallestFactors(
                        $returnDeliveryNoteItem->expected_quantity
                    )
                ),
                $returnDeliveryNoteItem->packed_in
            ),
            'total_item_not_returned'               => $returnDeliveryNoteItem->total_item_not_returned,
            'total_item_not_returned_fractional'    =>  riseDivisor(
                divideWithRemainder(
                    findSmallestFactors(
                        $returnDeliveryNoteItem->total_item_not_returned
                    )
                ),
                $returnDeliveryNoteItem->packed_in
            ),
            'total_item_damaged'                    => $returnDeliveryNoteItem->total_item_damaged,
            'total_item_damaged_fractional'         =>  riseDivisor(
                divideWithRemainder(
                    findSmallestFactors(
                        $returnDeliveryNoteItem->total_item_damaged
                    )
                ),
                $returnDeliveryNoteItem->packed_in
            ),
            'total_item_lost'                       => $returnDeliveryNoteItem->total_item_lost,
            'total_item_lost_fractional'            =>  riseDivisor(
                divideWithRemainder(
                    findSmallestFactors(
                        $returnDeliveryNoteItem->total_item_lost
                    )
                ),
                $returnDeliveryNoteItem->packed_in
            ),
            'total_item_returned'                   => $returnDeliveryNoteItem->total_item_returned,
            'total_item_returned_fractional'        =>  riseDivisor(
                divideWithRemainder(
                    findSmallestFactors(
                        $returnDeliveryNoteItem->total_item_returned
                    )
                ),
                $returnDeliveryNoteItem->packed_in
            ),
            'org_stock_id'                          => $returnDeliveryNoteItem->org_stock_id,
            'org_stock_code'                        => $returnDeliveryNoteItem->org_stock_code,
            'org_stock_name'                        => $returnDeliveryNoteItem->org_stock_name,
            'org_stock_slug'                        => $returnDeliveryNoteItem->org_stock_slug,
            'packed_in'                             => $returnDeliveryNoteItem->packed_in,
        ];
    }
}
