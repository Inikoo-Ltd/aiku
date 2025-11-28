<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Nov 2024 10:24:31 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Helpers\Address\FixedAddressGarbageCollection;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Models\Dispatching\DeliveryNote;
use App\Rules\ValidAddress;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteFixedAddress extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithModelAddressActions;


    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $oldAddress = $deliveryNote->deliveryAddress;


        if ($oldAddress and $oldAddress->checksum == $modelData['address']->getChecksum()) {
            return $deliveryNote;
        }

        $deliveryNote->fixedAddresses()->detach($oldAddress->id);


        $address = $this->createFixedAddress($deliveryNote, $modelData['address'], 'Ordering', 'delivery', 'address_id');

        $deliveryNote->updateQuietly(
            [
                'delivery_country_id' => $address->country_id,
            ]
        );

        if ($oldAddress) {
            FixedAddressGarbageCollection::dispatch($oldAddress->id)->delay($this->hydratorsDelay);
        }


        return $deliveryNote;
    }

    public function rules(): array
    {
        return [

            'address' => ['required', new ValidAddress()],

        ];
    }

    public function action(DeliveryNote $deliveryNote, array $modelData, int $hydratorsDelay = 0, bool $audit = true): DeliveryNote
    {
        if (!$audit) {
            DeliveryNote::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromShop($deliveryNote->shop, $modelData);

        return $this->handle($deliveryNote, $this->validatedData);
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $this->validatedData);
    }
}
