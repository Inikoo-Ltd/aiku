<?php

/*
 * author Louis Perez
 * created on 15-05-2026-11h-13m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote;

use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithHydrateReturnDeliveryNotes;
use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithReturnDeliveryNoteController;
use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithReturnDeliveryNoteTransition;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\UpdateReturnDeliveryNoteItem;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\UpsertReturnDeliveryNoteItemNotReturned;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use Illuminate\Support\Facades\DB;

class SetReturnedReturnDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use WithHydrateReturnDeliveryNotes;
    use WithReturnDeliveryNoteController;
    use WithReturnDeliveryNoteTransition;

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ReturnDeliveryNote $returnDeliveryNote): ReturnDeliveryNote
    {
        $this->validateReturnDeliveryNoteState($returnDeliveryNote, ReturnDeliveryNoteStateEnum::RETURNING);

        $modelData = [
            'state'           => ReturnDeliveryNoteStateEnum::RETURNED,
            'handler_user_id' => request()->user()->id,
        ];

        $returnDeliveryNote = DB::transaction(function () use ($returnDeliveryNote, $modelData) {
            $returnDeliveryNote = UpdateReturnDeliveryNote::make()->action($returnDeliveryNote, $modelData);

            foreach ($returnDeliveryNote->returnDeliveryNoteItem as $item) {
                $updatedData = [
                    'state'        => ReturnDeliveryNoteItemStateEnum::PROCESSED,
                ];
                $qtyReturned     = $item->total_item_damaged + $item->total_item_returned;
                $qtyNotReturned  = $item->total_expected_qty - $qtyReturned;

                if ($qtyNotReturned > 0) {
                    UpsertReturnDeliveryNoteItemNotReturned::make()->action($item, [
                        'quantity' => $qtyNotReturned,
                    ]);
                }

                UpdateReturnDeliveryNoteItem::make()->action($item, $updatedData);

                $deliveryNoteItem = $item->deliveryNoteItems;

                $deliveryNoteItem->update([
                    'quantity_returned' => ($deliveryNoteItem->returnDeliveryNoteItems()->sum('total_item_damaged') + $deliveryNoteItem->returnDeliveryNoteItems()->sum('total_item_returned')),
                ]);
            }

            return $returnDeliveryNote;
        });

        $this->hydrateReturnDeliveryNotes($returnDeliveryNote);

        return $returnDeliveryNote;
    }

}
