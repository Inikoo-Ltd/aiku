<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Feb 2026 12:25:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteStateToHandlingBlocked extends OrgAction
{
    use WithActionUpdate;

    private DeliveryNote $deliveryNote;

    /**
     * Blocked is a fact about the lines, not a state anyone can put a note into. Asked for on a
     * note with nothing blocking it, this leaves the note alone rather than strand it.
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        if (!$deliveryNote->hasBlockingItems()) {
            return $deliveryNote;
        }

        data_set($modelData, 'handling_blocked_at', now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::HANDLING_BLOCKED->value);

        //todo update order state

        return $this->update($deliveryNote, $modelData);
    }


    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

    public function action(DeliveryNote $deliveryNote): DeliveryNote
    {
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
