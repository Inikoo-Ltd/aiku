<?php

/*
 * author Arya Permana - Kirin
 * created on 28-05-2025-09h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Shipment;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\Shipment;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Dispatching\DeliveryNoteResource;
use App\Models\Dispatching\DeliveryNote;

class DeleteShipment extends OrgAction
{
    use WithActionUpdate;

    public function handle(Shipment $shipment): void
    {
        foreach ($shipment->deliveryNotes as $deliveryNote) {
            $deliveryNote->shipments()->detach($shipment);
        }

        $shipment->forceDelete();
    }

    public function asController(Shipment $shipment, ActionRequest $request): void
    {
        $this->initialisation($shipment->organisation, $request);

        $this->handle($shipment);
    }


}
