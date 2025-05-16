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
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Models\Dispatching\Shipment;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class DetachShipmentFromPalletReturn extends OrgAction
{
    use WithActionUpdate;
    use WithFulfilmentShopEditAuthorisation;



    private PalletReturn $palletReturn;

    public function handle(PalletReturn $palletReturn, array $modelData): PalletReturn
    {


        $shipmentsId = $modelData['shipments_id'] ?? null;

        $palletReturn->shipments()->detach($shipmentsId);


        $palletReturn->refresh();
        return $palletReturn;
    }

    public function rules(): array
    {
        return [
            'shipments_id' => ['required', 'array'],
            'shipments_id.*' => ['required', 'integer', Rule::exists(Shipment::class, 'id')->where('organisation_id', $this->organisation->id)],
        ];
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->organisation = $palletReturn->organisation;
        $this->initialisationFromWarehouse($palletReturn->warehouse, $request);

        return $this->handle($palletReturn, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, array $modelData, int $hydratorsDelay = 0): PalletReturn
    {
        $this->organisation = $palletReturn->organisation;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $modelData);

        return $this->handle($palletReturn, $this->validatedData);
    }

    public function jsonResponse(PalletReturn $palletReturn): PalletReturnResource
    {
        return new PalletReturnResource($palletReturn);
    }
}
