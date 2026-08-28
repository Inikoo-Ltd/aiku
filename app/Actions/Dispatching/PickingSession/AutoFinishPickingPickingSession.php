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
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\PickingSession;
use Illuminate\Console\Command;

class AutoFinishPickingPickingSession extends OrgAction
{
    use WithActionUpdate;

    public function handle(PickingSession $pickingSession): PickingSession
    {
        $oldState = $pickingSession->state;

        $numberItems = DeliveryNoteItem::where('picking_session_id', $pickingSession->id)->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)->count();

        $numberHandled = DeliveryNoteItem::where('picking_session_id', $pickingSession->id)
            ->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
            ->where('is_handled', true)
            ->count();

        $hasWaitingItems = DeliveryNoteItem::where('picking_session_id', $pickingSession->id)
            ->where('state', DeliveryNoteItemStateEnum::HANDLING_BLOCKED)
            ->exists();

        if ($numberHandled == $numberItems) {
            $updatedData = [
                'state' => $hasWaitingItems ? PickingSessionStateEnum::HANDLING_BLOCKED : PickingSessionStateEnum::PICKING_FINISHED
            ];

            if ($oldState == PickingSessionStateEnum::HANDLING_BLOCKED && !$hasWaitingItems) {
                data_set($updatedData, 'is_done_waiting', true);
            }

            $this->update($pickingSession, $updatedData);
            WarehouseHydratePickingSessions::dispatch($pickingSession->warehouse);
        }

        return $pickingSession;
    }

    public function getCommandSignature(): string
    {
        return 'auto-finish-picking-picking-session {picking_session}';
    }

    public function asCommand(Command $command): int
    {
        $pickingSession = PickingSession::where('slug', $command->argument('picking_session'))->firstOrFail();
        $this->handle($pickingSession);

        return 0;
    }


}
