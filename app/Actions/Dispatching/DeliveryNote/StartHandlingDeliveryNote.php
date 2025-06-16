<?php

/*
 * author Arya Permana - Kirin
 * created on 23-05-2025-14h-36m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateItems;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StartHandlingDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    protected User $user;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        if ($deliveryNote->state == DeliveryNoteStateEnum::UNASSIGNED) {
            $deliveryNote = UpdateDeliveryNoteStateToInQueue::make()->action($deliveryNote, $this->user);
        }


        data_set($modelData, 'handling_at', now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::HANDLING->value);
        data_set($modelData, 'picker_user_id', $this->user->id);



        return DB::transaction(function () use ($deliveryNote, $modelData) {

            UpdateDeliveryNote::run($deliveryNote, $modelData);

            DB::table('delivery_note_items')
                ->where('delivery_note_id', $deliveryNote->id)
                ->update(['state' => DeliveryNoteItemStateEnum::HANDLING->value]);

            DeliveryNoteHydrateItems::dispatch($deliveryNote)->delay($this->hydratorsDelay);

            return $deliveryNote;

        });



    }


    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->user = $request->user();
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $this->user     = $user;
        $this->asAction = true;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
