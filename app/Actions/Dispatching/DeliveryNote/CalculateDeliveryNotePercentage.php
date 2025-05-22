<?php
/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-15h-44m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Actions\Traits\WithOrganisationsArgument;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;

class CalculateDeliveryNotePercentage extends OrgAction
{

    public function handle(DeliveryNote $deliveryNote): void
    {
        $pickingPercentage = 0;
        $packingPercentage = 0;

        $pickingRequired = $deliveryNote->deliveryNoteItems()->sum('required_quantity');
        $pickingPicked = $deliveryNote->deliveryNoteItems()->sum('picked_quantity');
        $packingPacked = $deliveryNote->deliveryNoteItems()->sum('packed_quantity');

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

        $deliveryNote->update([
            'picking_percentage' => $pickingPercentage,
            'packing_percentage' => $packingPercentage
        ]);
    }
}
