<?php

/*
 * author Louis Perez
 * created on 12-03-2026-09h-47m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\Packing\DeletePacking;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToPacking;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteItemUnpack extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;
    use HasDeliveryNoteHydrators;

    protected User $user;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): DeliveryNoteItem
    {
        $deliveryNote = $deliveryNoteItem->deliveryNote;
        $oldState = $deliveryNote->state;

        $deliveryNoteItem->packings->each(function ($packing) {
            DeletePacking::run($packing);
        });

        $deliveryNoteItem->update([
            'state'           => DeliveryNoteItemStateEnum::HANDLING->value,
            'is_packed'       => false,
            'end_packing'     => null,
            'quantity_packed' => 0

        ]);

        $deliveryNote = $deliveryNoteItem->deliveryNote;
        $siblingDeliveryNoteItems = $deliveryNote->deliveryNoteItems()->with('packings')->get();

        $hasUnfinishedPackings = $siblingDeliveryNoteItems->filter(fn ($item) => empty((float) $item->quantity_not_picked) && $item->packings->count() == 0);

        $siblingDeliveryNoteItems = $deliveryNote->deliveryNoteItems()->with('packings')->get();

        $hasUnfinishedPackings = $siblingDeliveryNoteItems->filter(fn ($item) => $item->packings->count() == 0);
        if ($oldState == DeliveryNoteStateEnum::PACKED && $hasUnfinishedPackings->count() > 0) {
            UpdateDeliveryNote::make()->action($deliveryNote, [
                'packed_at' => null,
                'packer_user_id' => $this->user->id,
                'state' => DeliveryNoteStateEnum::PACKING->value,
            ]);
            if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                UpdateOrderStateToPacking::make()->action($deliveryNote->orders->first(), true);
            }
            $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
            $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::PACKING);
        }



        return $deliveryNoteItem;
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->user = $request->user();

        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
