<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 11:40:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateTrolleys;
use App\Actions\Dispatching\DeliveryNoteItem\CheckAndCompleteDeliveryNotePacking;
use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteStatePacked extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    private DeliveryNote $deliveryNote;
    protected User $user;


    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $oldState = $deliveryNote->state;

        data_set($modelData, 'packed_at', now());
        data_set($modelData, 'packer_user_id', $this->user->id);
        data_set($modelData, 'state', DeliveryNoteStateEnum::PACKED->value);


        $deliveryNote = DB::transaction(function () use ($deliveryNote, $modelData) {
            foreach ($deliveryNote->deliveryNoteItems->filter(fn ($item) => $item->packings->isEmpty()) as $item) {
                StorePacking::make()->action($item, $this->user, []);
            }

            $deliveryNote = CheckAndCompleteDeliveryNotePacking::run($deliveryNote);

            return $deliveryNote;
        });

        $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
        $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::PACKED);
        DeliveryNoteHydrateTrolleys::dispatch($deliveryNote->id);
        return $deliveryNote;
    }


    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->user         = $request->user();
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }


    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $this->user         = $user;
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
