<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Sept 2025 11:03:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SaveDeliveryNoteShippingFieldsAndRetryStoreShipping extends OrgAction
{
    use WithDeliveryNoteShipmentAuthorisation;
    use WithActionUpdate;

    private DeliveryNote $deliveryNote;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(DeliveryNote $deliveryNote, Shipper $shipper, array $modelData): Shipment
    {
        $addressData = Arr::get($modelData, 'address', []);

        $this->updateDeliveryAddress($deliveryNote, $addressData);

        $deliveryNote = $this->update($deliveryNote, Arr::only($modelData, ['email', 'phone', 'company_name', 'contact_name']));
        $deliveryNote->refresh();

        try {
            return StoreShipment::run($deliveryNote, $shipper, [], false);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(
                [$e->getMessage()]
            );
        }
    }


    public function updateDeliveryAddress(DeliveryNote $deliveryNote, array $addressData): void
    {
        if (!Arr::get($addressData, 'country_id')) {
            return;
        }
        if (!$deliveryNote->isDeliveryAddressEditable()) {
            throw ValidationException::withMessages([
                'address' => __('The delivery address can only be changed before a shipment is created and while the delivery note is not dispatched or cancelled.'),
            ]);
        }
        UpdateDeliveryNoteDeliveryAddress::make()->handle($deliveryNote, ['address' => $addressData]);
    }

    public function rules(): array
    {
        return [

            'email'        => ['sometimes', 'nullable', 'string'],
            'phone'        => ['sometimes', 'nullable', 'string'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address'      => ['sometimes', 'array', new ValidAddress()],


        ];
    }


    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, Shipper $shipper, ActionRequest $request, int $hydratorsDelay = 0): Shipment
    {
        $this->deliveryNote   = $deliveryNote;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $shipper, $this->validatedData);
    }
}
