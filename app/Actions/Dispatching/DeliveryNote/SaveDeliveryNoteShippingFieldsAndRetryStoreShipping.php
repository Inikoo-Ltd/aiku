<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Sept 2025 11:03:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class SaveDeliveryNoteShippingFieldsAndRetryStoreShipping extends OrgAction
{
    use WithActionUpdate;

    private DeliveryNote $deliveryNote;

    public function handle(DeliveryNote $deliveryNote, Shipper $shipper, array $modelData): Shipment
    {
        $addressData = Arr::get($modelData, 'address', []);
        UpdateAddress::run($deliveryNote->deliveryAddress, $addressData);

        $deliveryNote = $this->update($deliveryNote, Arr::only($modelData, ['email', 'phone', 'company_name', 'contact_name']));

        return StoreShipment::run($deliveryNote, $shipper, [], false);
    }

    public function rules(): array
    {
        return [

            'email'        => ['required', 'nullable', 'string'],
            'phone'        => ['required', 'nullable', 'string'],
            'company_name' => ['required', 'nullable', 'string', 'max:255'],
            'contact_name' => ['required', 'nullable', 'string', 'max:255'],
            'address'      => ['required', 'array'],


        ];
    }


    public function asController(DeliveryNote $deliveryNote, Shipper $hipper, ActionRequest $request, int $hydratorsDelay = 0): Shipment
    {
        $this->deliveryNote   = $deliveryNote;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $hipper, $this->validatedData);
    }
}
