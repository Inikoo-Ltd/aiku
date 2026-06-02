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
use Illuminate\Console\Command;

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

    public function getCommandSignature(): string
    {
        return 'dispatching:delivery-note:auto-finish-waiting {delivery_note}';
    }

    public function asCommand(Command $command): int
    {

        $deliveryNote = DeliveryNote::where('slug', $command->argument('delivery_note'))->firstOrFail();
        $this->handle($deliveryNote);
        return 0;
    }



}
