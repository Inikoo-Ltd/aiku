<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateItems;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteStateToInQueue extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    private DeliveryNote $deliveryNote;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $oldState = $deliveryNote->state;
        data_set($modelData, 'queued_at', now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::QUEUED->value);
        data_set($modelData, 'picker_user_id', $user->id);


        $deliveryNote = DB::transaction(function () use ($deliveryNote, $modelData) {
            UpdateDeliveryNote::run($deliveryNote, $modelData);

            DB::table('delivery_note_items')
                ->where('delivery_note_id', $deliveryNote->id)
                ->update(['state' => DeliveryNoteItemStateEnum::QUEUED->value]);


            return $deliveryNote;
        });

        DeliveryNoteHydrateItems::dispatch($deliveryNote)->delay($this->hydratorsDelay);
        $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
        $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::QUEUED);


        return $deliveryNote;
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, User $user, ActionRequest $request): DeliveryNote
    {
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $user);
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote, $user);
    }
}
