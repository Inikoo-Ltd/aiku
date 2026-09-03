<?php

/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-15h-44m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;
use Illuminate\Console\Command;

class AutoFinishPackingPickingSession extends OrgAction
{
    use WithActionUpdate;

    public function handle(PickingSession $pickingSession): PickingSession
    {
        $numberPacked = $pickingSession->deliveryNotes->whereIn('state', [DeliveryNoteStateEnum::PACKED, DeliveryNoteStateEnum::FINALISED, DeliveryNoteStateEnum::DISPATCHED])->count();

        $totalNumberDeliveryNotes = $pickingSession->deliveryNotes->where('state', '!=', DeliveryNoteStateEnum::CANCELLED)->count();
        $modelData = [];

        /*
         * Packing is the answer to the question the icon asks, so once a note of this session has
         * been packed the flag has done its job.
         */
        if ($pickingSession->is_done_waiting) {
            $modelData['is_done_waiting'] = false;
        }
        
        $packingIsFinished = $numberPacked == $totalNumberDeliveryNotes;

        if ($packingIsFinished) {
            $modelData['state']  = PickingSessionStateEnum::PACKING_FINISHED;
            $modelData['end_at'] = now();
        }

        if ($modelData) {
            $this->update($pickingSession, $modelData);
        }

        if ($packingIsFinished) {
            WarehouseHydratePickingSessions::dispatch($pickingSession->warehouse);
        }

        return $pickingSession;
    }


    public function getCommandSignature(): string
    {
        return 'auto-finish-packing-picking-session {picking_session}';
    }

    public function asCommand(Command $command): int
    {
        $pickingSession = PickingSession::where('slug', $command->argument('picking_session'))->firstOrFail();
        $this->handle($pickingSession);

        return 0;
    }


}
