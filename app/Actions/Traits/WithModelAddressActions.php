<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 May 2024 23:16:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Helpers\Address\Hydrators\AddressHydrateUsage;
use App\Models\Helpers\Address;
use App\Models\SysAdmin\Group;

trait WithModelAddressActions
{
    protected function updateLocation($model, $address)
    {
        $model->updateQuietly(
            [
                'location' => $address->getLocation()
            ]
        );

        return $model;
    }

    protected function updateAddressField($model, $address, $field = 'address_id')
    {
        $model->updateQuietly(
            [
                $field => $address->id
            ]
        );

        return $model;
    }


    protected function updateModelAddress($model, array $addressData, $updateLocation = true, $updateAddressField = 'address_id')
    {
        if (!$addressData) {
            return $model;
        }
        if ($model->address) {
            $address = $model->address;
            $address->update($addressData);

            if ($updateLocation) {
                $this->updateLocation($model, $address);
            }


            return $model;
        } else {
            return $this->addAddressToModelFromArray(model: $model, addressData: $addressData, updateLocation: $updateLocation, updateAddressField: $updateAddressField);
        }
    }

    protected function addAddressToModelFromArray($model, array $addressData, $scope = 'default', $updateLocation = true, $updateAddressField = 'address_id', bool $canShip = null)
    {
        if (!$addressData) {
            return $model;
        }

        $groupId = $model->group_id;
        if ($model instanceof Group) {
            $groupId = $model->id;
        }
        data_set($addressData, 'group_id', $groupId);

        $label = $addressData['label'] ?? null;

        unset($addressData['label']);


        data_forget($addressData, 'id');

        $address = Address::create($addressData);

        $pivotData = [
            'scope'    => $scope,
            'group_id' => $groupId
        ];

        if ($canShip === null and $scope == 'delivery') {
            $canShip = true;
        }

        if ($canShip !== null) {
            $pivotData['can_ship'] = $canShip;
        }


        $pivotData['label'] = $label;

        $model->addresses()->attach(
            $address->id,
            $pivotData
        );

        AddressHydrateUsage::dispatch($address);

        if ($updateLocation) {
            $this->updateLocation($model, $address);
        }
        if ($updateAddressField) {
            $this->updateAddressField($model, $address, $updateAddressField);
        }

        return $model;
    }

    protected function addDirectAddress($model, $addressData)
    {
        data_set($addressData, 'is_fixed', true);
        data_set($addressData, 'usage', 1);

        $groupId = $model->group_id;
        if ($model instanceof Group) {
            $groupId = $model->id;
        }
        data_set($addressData, 'group_id', $groupId);

        $address = Address::create($addressData);

        $model->updateQuietly(
            [
                'address_id' => $address->id,
                'location'   => $address->getLocation()
            ]
        );

        return $model;
    }

    protected function addLinkedAddress($model, $scope = 'default', $updateLocation = true, $updateAddressField = 'address_id')
    {
        $addressLink  = explode(':', $model->settings['address_link']);
        $address      = null;
        $addressModel = null;
        if ($addressLink[0] == 'Organisation') {
            $addressModel = $model->organisation;
        }


        if ($addressModel) {
            $address = $addressModel->addresses()->where('scope', $addressLink[1])->first();
        }

        $groupId = $model->group_id;
        if ($model instanceof Group) {
            $groupId = $model->id;
        }

        if ($address) {
            $model->addresses()->attach(
                $address->id,
                [
                    'scope'    => $scope,
                    'group_id' => $groupId
                ]
            );
            AddressHydrateUsage::dispatch($address);
            if ($updateLocation) {
                $this->updateLocation($model, $address);
            }
            if ($updateAddressField) {
                $this->updateAddressField($model, $address, $updateAddressField);
            }
        }


        return $model;
    }


    protected function attachAddressToModel($model, Address $address, $scope = 'default', $updateLocation = true, $updateAddressField = 'address_id', bool $canShip = null)
    {
        $groupId = $model->group_id;
        if ($model instanceof Group) {
            $groupId = $model->id;
        }

        $pivotData = [
            'scope'    => $scope,
            'group_id' => $groupId
        ];

        if ($canShip === null && $scope == 'delivery') {
            $canShip = true;
        }

        if ($canShip !== null) {
            $pivotData['can_ship'] = $canShip;
        }


        $model->addresses()->attach(
            $address->id,
            $pivotData
        );

        AddressHydrateUsage::dispatch($address);

        if ($updateLocation) {
            $this->updateLocation($model, $address);
        }
        if ($updateAddressField) {
            $this->updateAddressField($model, $address, $updateAddressField);
        }

        return $model;
    }




}
