<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 02 Apr 2026 18:34:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\Packing\DeletePacking;
use App\Actions\Dispatching\Picking\DeletePicking;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNoteItem;

class DeleteDeliveryNoteItem extends OrgAction
{
    use WithActionUpdate;


    public function handle(DeliveryNoteItem $deliveryNoteItem): DeliveryNoteItem
    {

        foreach ($deliveryNoteItem->pickings as $picking) {
            DeletePicking::run($picking, null);
        }
        foreach ($deliveryNoteItem->packings as $packing) {
            DeletePacking::run($packing);
        }
        $deliveryNoteItem->delete();
        return $deliveryNoteItem;
    }




}
