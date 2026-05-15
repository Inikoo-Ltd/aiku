<?php

/*
 * author Louis Perez
 * created on 06-05-2026-00h-00m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNoteItem;

use App\Actions\GoodsIn\Sowing\StoreSowing;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SetReturnDeliveryNoteItemAsNotReturned extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(ReturnDeliveryNoteItem $returnDeliveryNoteItem): ReturnDeliveryNoteItem
    {
        $modelData = [];
        $user = auth()->user();

        $totalItemNotReturned = $returnDeliveryNoteItem->total_expected_qty - (
            $returnDeliveryNoteItem->total_item_damaged +
            $returnDeliveryNoteItem->total_item_not_returned +
            $returnDeliveryNoteItem->total_item_returned
        );

        data_set($modelData, 'quantity', $totalItemNotReturned);
        data_set($modelData, 'sower_user_id', $user->id);

        data_set($modelData, 'type', SowingTypeEnum::NOT_SOW);

        StoreSowing::make()->action($returnDeliveryNoteItem, $user, $modelData);
        CalculateReturnDeliveryNoteItemTotalSowed::make()->action($returnDeliveryNoteItem);

        return $returnDeliveryNoteItem;
    }

    public function asController(ReturnDeliveryNoteItem $returnDeliveryNoteItem, ActionRequest $request): void
    {
        $this->initialisationFromWarehouse($returnDeliveryNoteItem->returnDeliveryNote->warehouse, $request);

        $this->handle($returnDeliveryNoteItem);
    }
}
