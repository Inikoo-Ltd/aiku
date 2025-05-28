<?php
/*
 * author Arya Permana - Kirin
 * created on 28-05-2025-08h-53m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Shipment\UI;

use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseEditAuthorisation;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Rule;

class CreateShipmentInDeliveryNoteInWarehouse extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, array $modelData): Shipment
    {
        $shipper = Shipper::find($modelData['shipper_id']);
        return StoreShipment::run($deliveryNote, $shipper, $modelData);
    }


    public function rules(): array
    {
        return [
            'tracking'       => ['sometimes', 'nullable', 'max:1000', 'string'],
            'shipper_id' => ['required', Rule::exists(Shipper::class, 'id')->where('organisation_id', $this->organisation->id)],
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request)
    {
        $this->initialisationFromWarehouse($deliveryNote->warehouse, $request);

        $this->handle($deliveryNote, $this->validatedData);
    }


}
