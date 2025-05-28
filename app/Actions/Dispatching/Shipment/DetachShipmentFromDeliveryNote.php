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

class DetachShipmentFromDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    private DeliveryNote $deliveryNote;

    public function handle(DeliveryNote $deliveryNote, Shipment $shipment): DeliveryNote
    {

        $deliveryNote->shipments()->detach($shipment);
        $shipment->forceDelete();

        $deliveryNote->refresh();
        return $deliveryNote;
    }

    public function asController(DeliveryNote $deliveryNote, Shipment $shipment, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromWarehouse($deliveryNote->warehouse, $request);

        return $this->handle($deliveryNote, $shipment);
    }


    public function jsonResponse(DeliveryNote $deliveryNote): DeliveryNoteResource
    {
        return new DeliveryNoteResource($deliveryNote);
    }
}
