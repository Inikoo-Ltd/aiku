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
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickingSession;

class CalculatePickingSessionPicks extends OrgAction
{
    use WithActionUpdate;
    public function handle(PickingSession $pickingSession): PickingSession
    {
        $pickingPercentage = 0;
        $packingPercentage = 0;

        $itemsRequired = $pickingSession->deliveryNotes()
            ->with('deliveryNoteItems')
            ->get()
            ->flatMap(function ($deliveryNote) {
                return $deliveryNote->deliveryNoteItems;
            })
            ->sum('quantity_required');
        $itemsPicked = $pickingSession->deliveryNotes()->sum('quantity_picked');
        $itemsPacked = $pickingSession->deliveryNotes()->sum('quantity_packed');

        // Picking percentage: picked vs required
        if ($itemsRequired > 0) {
            $pickingPercentage = min(($itemsPicked / $itemsRequired) * 100, 100);
        }

        // Packing percentage: packed vs picked
        if ($itemsPicked > 0) {
            $packingPercentage = min(($itemsPacked / $itemsPicked) * 100, 100);
        }

        // Optionally round them
        $pickingPercentage = round($pickingPercentage, 2);
        $packingPercentage = round($packingPercentage, 2);

        $pickingSession = $this->update($pickingSession, [
            'quantity_picked'       => $itemsPicked,
            'quantity_packed'       => $itemsPacked,
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
}
