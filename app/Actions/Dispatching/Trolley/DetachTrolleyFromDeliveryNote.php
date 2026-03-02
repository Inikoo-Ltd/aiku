<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 21:25:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Trolley;
use Lorisleiva\Actions\ActionRequest;

class DetachTrolleyFromDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use WithWarehouseEditAuthorisation;


    public function handle(Trolley $trolley, DeliveryNote $deliveryNote): void
    {
        $trolley->deliveryNotes()->detach($deliveryNote->id);
        UpdateTrolley::run($trolley, [
            'current_delivery_note_id' => null
        ]);
    }

    public function asController(DeliveryNote $deliveryNote, Trolley $trolley, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);
        $this->handle($trolley, $deliveryNote);
    }


}
