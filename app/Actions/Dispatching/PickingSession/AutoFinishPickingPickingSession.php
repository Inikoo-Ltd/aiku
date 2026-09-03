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
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\PickingSession;
use Illuminate\Console\Command;

class AutoFinishPickingPickingSession extends OrgAction
{
    use WithActionUpdate;

    public function handle(PickingSession $pickingSession): PickingSession
    {
        $numberItems = DeliveryNoteItem::where('picking_session_id', $pickingSession->id)->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)->count();

        $numberHandled = DeliveryNoteItem::where('picking_session_id', $pickingSession->id)
            ->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
            ->where('is_handled', true)
            ->count();
        
        $pickingIsFinished = $numberHandled == $numberItems;

        $modelData = [];

        /*
         * The flag is what the picking sessions list draws its icon from: this session waited on
         * stock and does not any more, so the notes it holds are ready for a packer. It is derived
         * on every pass rather than stamped once, so a session that goes back to waiting - an undo,
         * a marketplace quantity change turning a line dirty - loses the icon again on the same
         * pass that re-blocks it, instead of showing waiting and done waiting at once.
         *
         * The session state deliberately stays out of this. Blocking is a fact about a delivery
         * note, answered by hasBlockingItems(), and holding the whole session away from
         * PICKING_FINISHED would stop a packer packing the notes of the session that are ready,
         * which is the opposite of what the icon is for.
         */
        $isDoneWaiting = $pickingIsFinished
            && $this->hadWaitingNotes($pickingSession)
            && !$this->hasWaitingNotes($pickingSession);

        if ($pickingSession->is_done_waiting != $isDoneWaiting) {
            $modelData['is_done_waiting'] = $isDoneWaiting;
        }

        if ($pickingIsFinished) {
            $modelData['state'] = PickingSessionStateEnum::PICKING_FINISHED;
        }

        if ($modelData) {
            $this->update($pickingSession, $modelData);
        }

        if ($pickingIsFinished) {
            WarehouseHydratePickingSessions::dispatch($pickingSession->warehouse);
        }
 
         return $pickingSession;
     }
 
    /**
     * A note stamps handling_blocked_at when picking finds it blocked, and the stamp outlives the
     * block, so it is what says this session waited at all. Without it every session that never
     * waited would take the icon the moment it finished picking.
     */
    protected function hadWaitingNotes(PickingSession $pickingSession): bool
    {
        return $pickingSession->deliveryNotes()
            ->where('delivery_notes.state', '!=', DeliveryNoteStateEnum::CANCELLED)
            ->whereNotNull('delivery_notes.handling_blocked_at')
            ->exists();
    }

    protected function hasWaitingNotes(PickingSession $pickingSession): bool
    {
        return $pickingSession->deliveryNotes()
            ->where('delivery_notes.state', '!=', DeliveryNoteStateEnum::CANCELLED)
            ->get()
            ->contains(fn (DeliveryNote $deliveryNote) => $deliveryNote->hasBlockingItems());
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
