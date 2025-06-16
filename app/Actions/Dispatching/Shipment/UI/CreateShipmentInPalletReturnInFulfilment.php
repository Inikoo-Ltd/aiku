<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 12:55:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\UI;

use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class CreateShipmentInPalletReturnInFulfilment extends OrgAction
{
    use WithFulfilmentShopEditAuthorisation;

    public function handle(PalletReturn $palletReturn, array $modelData): Shipment
    {
        $shipper = Shipper::find($modelData['shipper_id']);

        return StoreShipment::run($palletReturn, $shipper, $modelData);
    }


    public function rules(): array
    {
        return [
            'tracking'   => ['sometimes', 'nullable', 'max:1000', 'string'],
            'shipper_id' => ['required', Rule::exists(Shipper::class, 'id')->where('organisation_id', $this->organisation->id)],
        ];
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): void
    {
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        $this->handle($palletReturn, $this->validatedData);
    }


}
