<?php

/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-15h-44m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;

class AutoFinishPackingPickingSession extends OrgAction
{
    use WithActionUpdate;
    public function handle(PickingSession $pickingSession): PickingSession
    {
        $numberPacked = $pickingSession->deliveryNotes->whereIn('state', [DeliveryNoteStateEnum::PACKED, DeliveryNoteStateEnum::FINALISED, DeliveryNoteStateEnum::DISPATCHED])->count();

        if ($numberPacked == $pickingSession->number_delivery_notes) {
            $this->update($pickingSession, [
                'state' => PickingSessionStateEnum::PACKING_FINISHED,
                'end_at' => now()
            ]);
        }
        return $pickingSession;
    }

    public function action(PickingSession $pickingSession): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, []);

        return $this->handle($pickingSession);
    }
}
