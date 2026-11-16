<?php

/*
 * author Louis Perez
 * created on 06-05-2026-00h-00m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNoteItem;

use App\Actions\Traits\Authorisations\Inventory\WithReturnsAuthorisation;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SetReturnDeliveryNoteItemAsNotReturned extends OrgAction
{
    use WithReturnsAuthorisation;
    use WithReturnItemPuts;
    use AsAction;
    use WithAttributes;

    public function handle(ReturnDeliveryNoteItem $returnDeliveryNoteItem): ReturnDeliveryNoteItem
    {
        $this->ensureReturnIsBeingReturned($returnDeliveryNoteItem);

        return $this->storeLockedPut($returnDeliveryNoteItem, [], null, SowingTypeEnum::NOT_SOW);
    }

    public function asController(ReturnDeliveryNoteItem $returnDeliveryNoteItem, ActionRequest $request): void
    {
        $this->initialisationFromWarehouse($returnDeliveryNoteItem->returnDeliveryNote->warehouse, $request);

        $this->handle($returnDeliveryNoteItem);
    }
}
