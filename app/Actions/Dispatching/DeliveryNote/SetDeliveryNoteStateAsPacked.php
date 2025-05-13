<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\Picking\PickingStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetDeliveryNoteStateAsPacked extends OrgAction
{
    use WithActionUpdate;

    private DeliveryNote $deliveryNote;

    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        data_set($modelData, 'packed_at', now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::PACKED->value);

        return $this->update($deliveryNote, $modelData);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $deliveryNoteItems = $this->deliveryNote->deliveryNoteItems;

        foreach ($deliveryNoteItems as $deliveryNoteItem) {
            $picking = $deliveryNoteItem->pickings;

            if (!$picking || $picking->state !== PickingStateEnum::DONE) {
                throw ValidationException::withMessages(['All items must be packed']);
            }
        }
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
