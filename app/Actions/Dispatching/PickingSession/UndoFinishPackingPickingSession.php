<?php

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;

class UndoFinishPackingPickingSession extends OrgAction
{
    use WithActionUpdate;

    public function handle(PickingSession $pickingSession): PickingSession
    {
        $numberPacked = $pickingSession->deliveryNotes->whereIn('state', [
            DeliveryNoteStateEnum::PACKED,
            DeliveryNoteStateEnum::FINALISED,
            DeliveryNoteStateEnum::DISPATCHED
        ])->count();

        if ($numberPacked == $pickingSession->number_delivery_notes) {
            $this->update($pickingSession, [
                'state'   => PickingSessionStateEnum::PICKING_FINISHED,
                'end_at'  => null
            ]);
            WarehouseHydratePickingSessions::dispatch($pickingSession->warehouse);
        }

        return $pickingSession;
    }

    public function action(PickingSession $pickingSession): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, []);

        return $this->handle($pickingSession);
    }
}
