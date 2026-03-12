<?php

/*
 * author Louis Perez
 * created on 12-03-2026-09h-47m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\Packing\DeletePacking;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteItemUnpack extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): DeliveryNoteItem
    {
        $deliveryNoteItem->packings->each(function ($packing) {
            DeletePacking::run($packing);
        });
        
        $deliveryNoteItem->update([
            'state'           => DeliveryNoteItemStateEnum::HANDLING->value,
            'is_packed'       => false,
            'end_packing'     => null,
            'quantity_packed' => 0

        ]);

        return $deliveryNoteItem;
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
