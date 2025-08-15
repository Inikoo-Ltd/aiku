<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-12h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\Dispatching\DeliveryNote\StartHandlingDeliveryNote;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;
use Lorisleiva\Actions\ActionRequest;

class StartPickPickingSession extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(PickingSession $pickingSession, array $modelData): PickingSession
    {
        data_set($modelData, 'state', PickingSessionStateEnum::HANDLING);
        data_set($modelData, 'start_at', now());

        $deliveryNotes = $pickingSession->deliveryNotes;

        foreach ($deliveryNotes as $deliveryNote) {
            $state = $deliveryNote->state;
            if ($state == DeliveryNoteStateEnum::UNASSIGNED ||
                $state == DeliveryNoteStateEnum::QUEUED
            ) {
                StartHandlingDeliveryNote::make()->action($deliveryNote, $pickingSession->user);
            }


        }

        return $this->update($pickingSession, $modelData);


    }

    /**
     * @throws \Throwable
     */
    public function asController(PickingSession $pickingSession, ActionRequest $request): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($pickingSession, $this->validatedData);
    }
}
