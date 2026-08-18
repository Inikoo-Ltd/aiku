<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 02 Apr 2026 18:34:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateItems;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
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
        $wasWaiting   = $deliveryNoteItem->has_waiting_crm || $deliveryNoteItem->has_waiting_warehouse;
        $deliveryNote = $deliveryNoteItem->deliveryNote;

        $deliveryNoteItem->delete();

        DeliveryNoteHydrateItems::run($deliveryNote);

        /**
         * A line waiting on CRM can be taken away by the source of the order itself, as Faire does
         * when the buyer edits it. The counts are what puts the delivery note in the waiting
         * buckets, so leaving them behind queues the order against an item that no longer exists
         * and nothing can clear it. Only a waiting line can move those counts.
         */
        if ($wasWaiting) {
            DeliveryNoteHydrateWaitingItems::run($deliveryNote->id);
        }

        return $deliveryNoteItem;
    }




}
