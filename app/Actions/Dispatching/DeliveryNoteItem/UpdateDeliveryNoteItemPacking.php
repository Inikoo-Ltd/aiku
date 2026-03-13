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
use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToPacked;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteItemPacking extends OrgAction
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

        StorePacking::make()->action($deliveryNoteItem, $this->user, []);

        $siblingDeliveryNoteItems = $deliveryNote->deliveryNoteItems()->with('packings')->get();

        $hasUnfinishedPackings = $siblingDeliveryNoteItems->filter(fn ($item) => $item->packings->count() == 0);
        if ($oldState != DeliveryNoteStateEnum::PACKED && $hasUnfinishedPackings->count() == 0) {

            foreach ($deliveryNote->trolleys as $trolley) {
                DB::table('delivery_note_has_trolleys')
                    ->where('delivery_note_id', $deliveryNote->id)->where('trolley_id', $trolley->id)->delete();
                $trolley->update(['current_delivery_note_id' => null]);
            }

            $defaultParcel = count($deliveryNote->parcels) == 0 ? [
                [
                    'weight'     => $deliveryNote->effective_weight / 1000,
                    'dimensions' => [5, 5, 5]
                ]
            ] : $deliveryNote->parcels;

            UpdateDeliveryNote::make()->action($deliveryNote, [
                'packed_at' => now(),
                'packer_user_id' => $this->user->id,
                'state' => DeliveryNoteStateEnum::PACKED->value,
                'parcels'   => $defaultParcel
            ]);


            if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                UpdateOrderStateToPacked::make()->action($deliveryNote->orders->first(), true);
            }
            $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
            $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::PACKED);
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
