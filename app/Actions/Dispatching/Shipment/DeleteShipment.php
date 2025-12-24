<?php

/*
 * author Arya Permana - Kirin
 * created on 28-05-2025-09h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Shipment;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateShipments;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateShipments;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\Shipment;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DeleteShipment extends OrgAction
{
    use WithActionUpdate;

    public function handle(Shipment $shipment): void
    {
        foreach ($shipment->deliveryNotes as $deliveryNote) {
            $deliveryNote->shipments()->detach($shipment);
            DeliveryNoteHydrateShipments::dispatch($deliveryNote->id)->delay(2);
            foreach (DB::table('delivery_note_order')->select('order_id')->where('delivery_note_id', $deliveryNote->id)->get() as $orderData) {
                OrderHydrateShipments::dispatch($orderData->order_id)->delay(2);
            }
        }

        $shipment->forceDelete();
    }

    public function asController(Shipment $shipment, ActionRequest $request): void
    {
        $this->initialisation($shipment->organisation, $request);

        $this->handle($shipment);
    }


}
