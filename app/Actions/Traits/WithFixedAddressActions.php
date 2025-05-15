<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 May 2024 13:58:23 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Helpers\Address\Hydrators\AddressHydrateFixedUsage;
use App\Models\Accounting\Invoice;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;

trait WithFixedAddressActions
{
    protected function findFixedAddress(Address $address, string $fixedScope): ?Address
    {
        return Address::where('checksum', $address->getChecksum())
            ->where('is_fixed', true)
            ->where('fixed_scope', $fixedScope)
            ->first();
    }


    protected function createFixedAddress(Order|Invoice|DeliveryNote|PalletReturn $model, Address $addressTemplate, string $fixedScope, $scope, $addressField): Address
    {
        $groupId = $model->group_id;

        if (!$address = $this->findFixedAddress($addressTemplate, $fixedScope)) {
            $modelData = $addressTemplate->toArray();
            data_set($modelData, 'is_fixed', true);
            data_set($modelData, 'fixed_scope', $fixedScope);
            data_set($modelData, 'group_id', $groupId);
            $modelData = Arr::only($modelData, [
                'group_id',
                'address_line_1',
                'address_line_2',
                'sorting_code',
                'postal_code',
                'dependent_locality',
                'locality',
                'administrative_area',
                'country_code',
                'country_id',
                'is_fixed',
                'fixed_scope'
            ]);

            $address = Address::create($modelData);
        }

        $model->fixedAddresses()->attach(
            $address->id,
            [
                'scope' => $scope,
                'group_id' => $groupId
            ]
        );

        AddressHydrateFixedUsage::dispatch($address);
        $model->updateQuietly([$addressField => $address->id]);

        return $address;
    }

    protected function updateFixedAddress(Order|Invoice|DeliveryNote $model, Address $currentAddress, Address $addressData, string $fixedScope, $scope, $addressField): Address
    {
        if ($currentAddress->checksum != $addressData->getChecksum()) {
            $model->fixedAddresses()->detach($currentAddress->id);
            AddressHydrateFixedUsage::dispatch($currentAddress);

            return $this->createFixedAddress($model, $addressData, $fixedScope, $scope, $addressField);
        }

        return $currentAddress;
    }

}
