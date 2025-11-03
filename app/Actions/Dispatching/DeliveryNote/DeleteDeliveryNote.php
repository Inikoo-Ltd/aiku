<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;

class DeleteDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $deliveryNote = DB::transaction(function () use ($deliveryNote, $modelData) {
            $deliveryNote = $this->update($deliveryNote, $modelData);

            foreach ($deliveryNote->deliveryNoteItems as $item) {
                $item->pickings()->delete();
            }
            $deliveryNote->deliveryNoteItems()->delete();
            $deliveryNote->delete();

            return $deliveryNote;
        });

        $this->storeDeliveryNoteHydrators($deliveryNote);
        $this->deliveryNoteHandlingHydrators($deliveryNote, $deliveryNote->state);

        return $deliveryNote;
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $modelData);

        return $this->handle($deliveryNote, $this->validatedData);
    }
}
