<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 10:50:40 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateItems;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteStateToUnassigned extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        data_set($modelData, 'queued_at', null);
        data_set($modelData, 'state', DeliveryNoteStateEnum::UNASSIGNED->value);
        data_set($modelData, 'picker_user_id', null);


        return DB::transaction(function () use ($deliveryNote, $modelData) {
            UpdateDeliveryNote::run($deliveryNote, $modelData);

            DB::table('delivery_note_items')
                ->where('delivery_note_id', $deliveryNote->id)
                ->update(['state' => DeliveryNoteItemStateEnum::UNASSIGNED->value]);

            DeliveryNoteHydrateItems::dispatch($deliveryNote)->delay($this->hydratorsDelay);

            return $deliveryNote;
        });
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote): DeliveryNote
    {
        $this->asAction = true;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
