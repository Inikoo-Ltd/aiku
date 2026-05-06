<?php

/*
 * author Louis Perez
 * created on 06-05-2026-00h-00m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNoteItem;

use App\Actions\OrgAction;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SetReturnDeliveryNoteItemAsDamaged extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(ReturnDeliveryNoteItem $returnDeliveryNoteItem): ReturnDeliveryNoteItem
    {
        dd('aaaaaa');
        // $expectedQuantity = $returnDeliveryNoteItem->deliveryNoteItems->quantity_dispatched;

        // $returnDeliveryNoteItem->update([
        //     'return_state'       => ReturnDeliveryNoteItemStateEnum::DAMAGED,
        //     'total_item_damaged' => $expectedQuantity,
        // ]);

        // return $returnDeliveryNoteItem;
    }

    public function asController(ReturnDeliveryNoteItem $returnDeliveryNoteItem, ActionRequest $request): ReturnDeliveryNoteItem
    {
        $this->initialisationFromShop($returnDeliveryNoteItem->shop, $request);

        return $this->handle($returnDeliveryNoteItem);
    }
}
