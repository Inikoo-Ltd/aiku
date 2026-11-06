<?php

/*
 * author Arya Permana - Kirin
 * created on 11-07-2025-11h-07m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Helpers\Address\FixedAddressGarbageCollection;
use App\Actions\OrgAction;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithStoreModelAddress;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use App\Rules\ValidAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteDeliveryAddress extends OrgAction
{
    use WithDeliveryNoteShipmentAuthorisation;
    use WithStoreModelAddress;
    use WithFixedAddressActions;

    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $addressData = Arr::only($modelData['address'], [
            'address_line_1',
            'address_line_2',
            'sorting_code',
            'postal_code',
            'locality',
            'dependent_locality',
            'administrative_area',
            'country_id',
        ]);

        $addressData['group_id']     = $deliveryNote->group_id;
        $addressData['country_code'] = Country::find($addressData['country_id'])?->code;
        if ($deliveryNote->delivery_locked) {
            $oldAddressId = $deliveryNote->address_id;
            $address      = $this->updateFixedAddress($deliveryNote, $deliveryNote->deliveryAddress, new Address($addressData), 'Ordering', 'delivery', 'address_id');
            if ($oldAddressId && $oldAddressId !== $address->id) {
                FixedAddressGarbageCollection::dispatch($oldAddressId)->delay($this->hydratorsDelay);
            }
        } else {
            $address = $this->storeModelAddress($addressData);
            $deliveryNote->updateQuietly(['address_id' => $address->id]);
        }
        $deliveryNote->update(['delivery_country_id' => $address->country_id]);

        return $deliveryNote;
    }

    public function rules(): array
    {
        return [
            'address' => ['required', 'array', new ValidAddress(requireFullAddress: true)],
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        if (!$deliveryNote->isDeliveryAddressEditable()) {
            throw ValidationException::withMessages([
                'address' => __('The delivery address can only be changed before a shipment is created and while the delivery note is not dispatched or cancelled.'),
            ]);
        }

        $this->initialisationFromWarehouse($deliveryNote->warehouse, $request);

        return $this->handle($deliveryNote, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
