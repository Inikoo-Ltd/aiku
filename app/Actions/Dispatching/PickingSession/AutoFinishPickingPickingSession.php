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
        if ($pickingSession->state == PickingSessionStateEnum::HANDLING_BLOCKED) {
            return UpdatePickingSessionStateFromHandlingBlocked::run($pickingSession);
        }

        $numberItems = DeliveryNoteItem::where('picking_session_id', $pickingSession->id)->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)->count();

        $numberHandled = DeliveryNoteItem::where('picking_session_id', $pickingSession->id)
            ->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
            ->where('is_handled', true)
            ->count();


        if ($numberHandled == $numberItems) {
            /*
             * Waiting lines count as handled, so picking can be finished while a note still waits.
             * The session waits with it rather than go to packing with that note incomplete.
             */
            $hasBlockedDeliveryNotes = $pickingSession->deliveryNotes()
                ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED)
                ->exists();

            $this->update($pickingSession, [
                'state' => $hasBlockedDeliveryNotes ? PickingSessionStateEnum::HANDLING_BLOCKED : PickingSessionStateEnum::PICKING_FINISHED
            ]);
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
