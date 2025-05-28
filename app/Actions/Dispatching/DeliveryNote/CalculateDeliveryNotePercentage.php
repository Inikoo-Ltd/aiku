<?php

/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-15h-44m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;

class CalculateDeliveryNotePercentage extends OrgAction
{
    use WithActionUpdate;
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $pickingPercentage = 0;
        $packingPercentage = 0;

        $pickingRequired = $deliveryNote->deliveryNoteItems()->sum('quantity_required');
        $pickingPicked = $deliveryNote->deliveryNoteItems()->sum('quantity_picked');
        $packingPacked = $deliveryNote->deliveryNoteItems()->sum('quantity_packed');

        // Picking percentage: picked vs required
        if ($pickingRequired > 0) {
            $pickingPercentage = ($pickingPicked / $pickingRequired) * 100;
        }

        // Packing percentage: packed vs picked
        if ($pickingPicked > 0) {
            $packingPercentage = ($packingPacked / $pickingPicked) * 100;
        }

        // Optionally round them
        $pickingPercentage = round($pickingPercentage, 2);
        $packingPercentage = round($packingPercentage, 2);

        $deliveryNote = $this->update($deliveryNote, [
            'picking_percentage' => $pickingPercentage,
            'packing_percentage' => $packingPercentage
        ]);

        return $deliveryNote;
    }
    
    public function action(DeliveryNote $deliveryNote): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
