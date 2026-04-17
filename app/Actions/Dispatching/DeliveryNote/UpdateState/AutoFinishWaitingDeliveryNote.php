<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Apr 2026 15:55:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;

class AutoFinishWaitingDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {

        if ($deliveryNote->state != DeliveryNoteStateEnum::HANDLING_BLOCKED) {
            return $deliveryNote;
        }

        $countWithWaitingWarehouse = $deliveryNote->deliveryNoteItems()->where('has_waiting_warehouse', true)->count();
        $countWithWaitingCrm = $deliveryNote->deliveryNoteItems()->where('has_waiting_crm', true)->count();
        if ($countWithWaitingWarehouse == 0 && $countWithWaitingCrm == 0) {
            UpdateDeliveryNoteStateToPicked::run($deliveryNote);
        }

        return $deliveryNote;
    }





}
