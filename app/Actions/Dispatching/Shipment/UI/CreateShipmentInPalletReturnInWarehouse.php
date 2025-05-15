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

class CreateShipmentInPalletReturnInWarehouse extends OrgAction
{
    use WithFulfilmentWarehouseEditAuthorisation;

    public function handle(PalletReturn $palletReturn, Shipper $shipper, array $modelData): Shipment
    {
        return StoreShipment::run($palletReturn, $shipper, $modelData);
    }


    public function rules(): array
    {
        return [
            'tracking'       => ['required', 'max:1000', 'string'],
            'number_parcels' => ['required', 'numeric', 'min:1'],
        ];
    }

    public function asController(PalletReturn $palletReturn, Shipper $shipper, ActionRequest $request): Shipment
    {
        $this->initialisationFromWarehouse($palletReturn->warehouse, $request);

        return $this->handle($palletReturn, $shipper, $this->validatedData);
    }


}
