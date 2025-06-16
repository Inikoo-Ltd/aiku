<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 16-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dispatching\Shipment;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Models\Dispatching\Shipment;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseEditAuthorisation;

class DetachShipmentFromPalletReturn extends OrgAction
{
    use WithActionUpdate;
    use WithFulfilmentWarehouseEditAuthorisation;



    private PalletReturn $palletReturn;

    public function handle(PalletReturn $palletReturn, Shipment $shipment): PalletReturn
    {

        $palletReturn->shipments()->detach($shipment);
        $shipment->forceDelete();

        $palletReturn->refresh();
        return $palletReturn;
    }

    public function asController(PalletReturn $palletReturn, Shipment $shipment, ActionRequest $request): PalletReturn
    {
        $this->initialisationFromWarehouse($palletReturn->warehouse, $request);

        return $this->handle($palletReturn, $shipment);
    }


    public function jsonResponse(PalletReturn $palletReturn): PalletReturnResource
    {
        return new PalletReturnResource($palletReturn);
    }
}
