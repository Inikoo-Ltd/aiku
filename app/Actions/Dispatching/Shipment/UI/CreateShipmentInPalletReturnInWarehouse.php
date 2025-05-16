<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 12:55:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\UI;

use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseEditAuthorisation;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Rule;

class CreateShipmentInPalletReturnInWarehouse extends OrgAction
{
    use WithFulfilmentWarehouseEditAuthorisation;

    public function handle(PalletReturn $palletReturn, array $modelData): Shipment
    {
        $shipper = Shipper::find($modelData['shipper_id']);
        return StoreShipment::run($palletReturn, $shipper, $modelData);
    }


    public function rules(): array
    {
        return [
            'tracking'       => ['sometimes', 'nullable', 'max:1000', 'string'],
            'shipper_id' => ['required', Rule::exists(Shipper::class, 'id')->where('organisation_id', $this->organisation->id)],
        ];
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): Shipment
    {
        $this->initialisationFromWarehouse($palletReturn->warehouse, $request);

        return $this->handle($palletReturn, $this->validatedData);
    }


}
