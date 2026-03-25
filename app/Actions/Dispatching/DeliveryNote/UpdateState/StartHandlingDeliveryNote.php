<?php

/*
 * author Arya Permana - Kirin
 * created on 23-05-2025-14h-36m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateItems;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydratePicker;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToHandling;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StartHandlingDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    protected User $user;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $oldState = $deliveryNote->state;

        if (in_array($deliveryNote->state, [
            DeliveryNoteStateEnum::QUEUED,
            DeliveryNoteStateEnum::UNASSIGNED,
            DeliveryNoteStateEnum::HANDLING_BLOCKED


        ])) {
            if ($deliveryNote->state == DeliveryNoteStateEnum::UNASSIGNED) {
                $deliveryNote = UpdateDeliveryNoteStateToInQueue::make()->action($deliveryNote, $user);
            }


            data_set($modelData, 'handling_at', now());
            data_set($modelData, 'state', DeliveryNoteStateEnum::HANDLING->value);
            data_set($modelData, 'picker_user_id', $user->id);


            $deliveryNote = DB::transaction(function () use ($deliveryNote, $modelData) {
                $deliveryNote = UpdateDeliveryNote::run($deliveryNote, $modelData);

                if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                    $order = $deliveryNote->orders->first();
                    if (in_array($order->state, [
                        OrderStateEnum::IN_WAREHOUSE,
                        OrderStateEnum::HANDLING,
                    ])) {
                        UpdateOrderStateToHandling::make()->action($order);
                    }
                }

                DB::table('delivery_note_items')
                    ->where('delivery_note_id', $deliveryNote->id)
                    ->update(['state' => DeliveryNoteItemStateEnum::HANDLING->value]);


                return $deliveryNote;
            });
            DeliveryNoteHydratePicker::dispatch($deliveryNote->id);
            DeliveryNoteHydrateItems::dispatch($deliveryNote)->delay($this->hydratorsDelay);

            $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
            $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::HANDLING);
        }

        return $deliveryNote;
    }


    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $request->user());
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $this->asAction = true;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote, $user);
    }
}
