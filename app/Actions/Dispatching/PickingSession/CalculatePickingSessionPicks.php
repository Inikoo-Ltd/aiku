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
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Inventory\PickingSession;
use Illuminate\Console\Command;

class CalculatePickingSessionPicks extends OrgAction
{
    use WithActionUpdate;

    public function handle(PickingSession $pickingSession): PickingSession
    {
        $pickingPercentage = 0;
        $packingPercentage = 0;

        // Sum of required minus not picked, guarding nulls and negatives
        $itemsRequired = (int)($pickingSession->deliveryNotesItems()
            ->where('delivery_note_items.state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
            ->selectRaw('SUM(GREATEST((quantity_required - COALESCE(quantity_not_picked, 0)), 0)) as total')
            ->value('total') ?? 0);

        $itemsPicked = $pickingSession->deliveryNotesItems()->sum('quantity_picked');
        $itemsPacked = $pickingSession->deliveryNotesItems()->sum('quantity_packed');

        // Picking percentage: picked vs. required
        if ($itemsRequired > 0) {
            $pickingPercentage = min(($itemsPicked / $itemsRequired) * 100, 100);
        }

        // Packing percentage: packed vs. picked
        if ($itemsPicked > 0) {
            $packingPercentage = min(($itemsPacked / $itemsPicked) * 100, 100);
        }

        // Optionally round them
        $pickingPercentage = round($pickingPercentage, 2);
        $packingPercentage = round($packingPercentage, 2);


        $pickingSession = $this->update($pickingSession, [
            'quantity_picked'    => $itemsPicked,
            'quantity_packed'    => $itemsPacked,
            'picking_percentage' => $pickingPercentage,
            'packing_percentage' => $packingPercentage
        ]);

        AutoFinishPickingPickingSession::run($pickingSession);

        return $pickingSession;
    }

    public function action(PickingSession $pickingSession): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, []);

        return $this->handle($pickingSession);
    }

    public function getCommandSignature(): string
    {
        return 'picking_session:calculate {picking_session}';
    }

    public function getCommandDescription(): string
    {
        return 'Calculate picking session picks';
    }

    public function asCommand(Command $command): int
    {
        $pickingSession = PickingSession::where('slug', $command->argument('picking_session'))->firstOrFail();

        $this->handle($pickingSession);

        return 0;
    }


}
