<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 20:53:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Helpers\Address;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

trait WithAddressAuditing
{
    protected function auditAddressChange(Model $auditModel, Address $address, array $oldAddressFields, string $addressLabel, bool $dispatchEvent = true): void
    {
        $newAddressFields = Arr::except($address->getFields(), 'country_id');

        $oldAddress = array_combine(
            array_map(fn ($key) => $addressLabel.' '.$key, array_keys($oldAddressFields)),
            $oldAddressFields
        );

        foreach ($oldAddress as $key => $value) {
            $fieldName = str_replace($addressLabel.' ', '', $key);
            if (array_key_exists($fieldName, $newAddressFields) && $value === $newAddressFields[$fieldName]) {
                unset($oldAddress[$key]);
                unset($newAddressFields[$fieldName]);
            }
        }

        if (!empty($oldAddress) || !empty($newAddressFields)) {
            $newAddress = array_combine(
                array_map(fn ($key) => $addressLabel.' '.$key, array_keys($newAddressFields)),
                $newAddressFields
            );

            $auditModel->auditEvent     = 'update';
            $auditModel->isCustomEvent  = true;
            $auditModel->auditCustomOld = $oldAddress;
            $auditModel->auditCustomNew = $newAddress;

            if ($dispatchEvent) {
                Event::dispatch(new AuditCustom($auditModel));
            }
        }
    }

    protected function auditNewAddress(Model $auditModel, Address $address, string $addressLabel, bool $dispatchEvent = true): void
    {
        $newAddressFields = array_filter(Arr::except($address->getFields(), 'country_id'));
        $newAddress = array_combine(
            array_map(fn ($key) => $addressLabel.' '.$key, array_keys($newAddressFields)),
            $newAddressFields
        );

        $oldAddress = $newAddress;
        foreach ($oldAddress as $key => $value) {
            $oldAddress[$key] = '';
        }

        $auditModel->auditEvent     = 'update';
        $auditModel->isCustomEvent  = true;
        $auditModel->auditCustomNew = $newAddress;
        $auditModel->auditCustomOld = $oldAddress;

        if ($dispatchEvent) {
            Event::dispatch(new AuditCustom($auditModel));
        }
    }
}
