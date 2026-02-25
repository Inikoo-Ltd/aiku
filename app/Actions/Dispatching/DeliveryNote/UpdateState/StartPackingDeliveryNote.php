<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateItems;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToHandling;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToPacking;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StartPackingDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    public function handle(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $oldState = $deliveryNote->state;

        if (in_array($deliveryNote->state, [
            DeliveryNoteStateEnum::CANCELLED,
            DeliveryNoteStateEnum::DISPATCHED,
            DeliveryNoteStateEnum::FINALISED,
            DeliveryNoteStateEnum::PACKING,

        ])) {
            return $deliveryNote;
        }

        data_set($modelData, 'packing_at', now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::PACKING->value);
        data_set($modelData, 'packer_user_id', $user->id);


    //    $deliveryNote = DB::transaction(function () use ($deliveryNote, $modelData) {
        $deliveryNote=UpdateDeliveryNote::run($deliveryNote, $modelData);

            if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                UpdateOrderStateToPacking::make()->action($deliveryNote->orders->first(), true);
            }

            DB::table('delivery_note_items')
                ->where('state', DeliveryNoteItemStateEnum::PICKED->value)
                ->where('delivery_note_id', $deliveryNote->id)
                ->update(['state' => DeliveryNoteItemStateEnum::PACKING->value]);

            DeliveryNoteHydrateItems::dispatch($deliveryNote)->delay($this->hydratorsDelay);

//            return $deliveryNote;
//        });


        $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
        $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::PACKING);

        return $deliveryNote;
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $request->user());
    }

    public function action(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote, $user);
    }
}
