<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Feb 2026 12:14:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PickedBay;

use App\Actions\Dispatching\PickedBay\Hydrators\PickedBayHydrateNumberDeliveryNotes;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickedBay;

class AttachDeliveryNoteToPickedBay extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithWarehouseEditAuthorisation;


    public function handle(PickedBay $pickedBay, DeliveryNote $deliveryNote): void
    {
        $pickedBay->deliveryNotes()->attach([
            $deliveryNote->id => [
                'group_id'        => $deliveryNote->group_id,
                'organisation_id' => $deliveryNote->organisation_id
            ]
        ]);
        PickedBayHydrateNumberDeliveryNotes::run($pickedBay->id);
    }


}
