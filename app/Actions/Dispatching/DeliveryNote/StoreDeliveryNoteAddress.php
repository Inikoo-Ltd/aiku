<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Nov 2024 21:11:58 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Actions\Traits\WithStoreModelAddress;
use App\Models\Dispatching\DeliveryNote;
use App\Rules\ValidAddress;

class StoreDeliveryNoteAddress extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithModelAddressActions;
    use WithStoreModelAddress;

    private DeliveryNote $deliveryNote;

    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $addressData = $modelData['address']->toArray();
        data_set($addressData, 'group_id', $deliveryNote->group_id);
        $address = $this->storeModelAddress($addressData);
        $deliveryNote->updateQuietly(
            [
                'address_id'          => $address->id,
                'delivery_country_id' => $address->country_id,
            ]
        );

        return $deliveryNote;
    }

    public function rules(): array
    {
        return [
            'address' => ['required', new ValidAddress(requireFullAddress: true)],
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


}
