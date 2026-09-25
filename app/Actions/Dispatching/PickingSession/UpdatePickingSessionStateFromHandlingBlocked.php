<?php

/*
 * Author Louis Perez
 * Created on 17-09-2026-09h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;
use Illuminate\Console\Command;

class UpdatePickingSessionStateFromHandlingBlocked extends OrgAction
{
    use WithActionUpdate;

    /**
     * A session waits as long as any of its notes waits. Once none does, it goes to packing when
     * every note has all its lines handled, otherwise back to picking. Either way it is flagged so
     * the packers can find it; the next action taken in the session clears the flag.
     */
    public function handle(PickingSession $pickingSession): PickingSession
    {
        if ($pickingSession->state != PickingSessionStateEnum::HANDLING_BLOCKED) {
            return $pickingSession;
        }

        $hasBlockedDeliveryNotes = $pickingSession->deliveryNotes()
            ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED)
            ->exists();

        if ($hasBlockedDeliveryNotes) {
            return $pickingSession;
        }

        $hasUnhandledItems = $pickingSession->deliveryNotesItems()
            ->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
            ->where('is_handled', false)
            ->exists();

        $this->update($pickingSession, [
            'state'            => $hasUnhandledItems ? PickingSessionStateEnum::HANDLING : PickingSessionStateEnum::PICKING_FINISHED,
            'is_waiting_ready' => true,
        ]);
        WarehouseHydratePickingSessions::dispatch($pickingSession->warehouse);

        return $pickingSession;
    }

    public function getCommandSignature(): string
    {
        return 'picking_session:update-state-from-handling-blocked {picking_session}';
    }

    public function asCommand(Command $command): int
    {
        $pickingSession = PickingSession::where('slug', $command->argument('picking_session'))->firstOrFail();
        $this->handle($pickingSession);

        return 0;
    }
}
