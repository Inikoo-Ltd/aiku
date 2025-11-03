<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Mar 2025 21:57:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;

class ForceDeleteDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $deliveryNote = DB::transaction(function () use ($deliveryNote) {
            foreach ($deliveryNote->deliveryNoteItems as $item) {
                $item->pickings()->forceDelete();
            }
            $deliveryNote->deliveryNoteItems()->forceDelete();
            $deliveryNote->forceDelete();

            return $deliveryNote;
        });


        $this->storeDeliveryNoteHydrators($deliveryNote);
        $this->deliveryNoteHandlingHydrators($deliveryNote, $deliveryNote->state);

        return $deliveryNote;
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
