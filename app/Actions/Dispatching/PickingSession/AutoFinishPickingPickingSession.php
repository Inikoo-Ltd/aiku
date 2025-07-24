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
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;

class AutoFinishPickingPickingSession extends OrgAction
{
    use WithActionUpdate;
    public function handle(PickingSession $pickingSession): PickingSession
    {
        $numberHandled = $pickingSession->deliveryNotes()
            ->with('deliveryNoteItems')
            ->get()
            ->flatMap(function ($deliveryNote) {
                return $deliveryNote->deliveryNoteItems;
            })
            ->where('is_handled', true)
            ->count();

        if ($numberHandled == $pickingSession->number_items) {
            $this->update($pickingSession, [
                'state' => PickingSessionStateEnum::PICKING_FINISHED
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
