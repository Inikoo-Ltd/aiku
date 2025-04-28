<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\WebUser\WebUserAuthTypeEnum;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraWebUser extends FetchAurora
{
    protected function parseModel(): void
    {
        $data = [];

        $customer = Customer::withTrashed()->where('source_id', $this->organisation->id.':'.$this->auroraModelData->{'Website User Customer Key'})->first();

        if (!$customer) {
            sleep(2);
            $customer = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Website User Customer Key'});
        }


        if (!$customer) {
            return;
        }

        if ($customer->shop->type == ShopTypeEnum::FULFILMENT) {
            return;
        }


        if (!$customer->shop->website) {
            return;
        }

        $this->parsedData['customer'] = $customer;

        $hasPassword = $this->isSha256($this->auroraModelData->{'Website User Password'});

        if ($hasPassword) {
            $authType = WebUserAuthTypeEnum::AURORA->value;

            $legacyPassword = $this->auroraModelData->{'Website User Password'};
            if (app()->isLocal() || app()->environment('testing')) {
                $legacyPassword = hash('sha256', 'hello');
            }

            $data['legacy_password'] = $legacyPassword;
            $password                = null;
        } else {
            $authType = WebUserAuthTypeEnum::DEFAULT->value;
            $password = (app()->isLocal() || app()->environment('testing') ? 'hello' : wordwrap(Str::random(), 4, '-', true));
        }


        $this->parsedData['webUser'] =
            [
                'status'          => $this->auroraModelData->{'Website User Active'} == 'Yes',
                'type'            => WebUserTypeEnum::WEB->value,
                'auth_type'       => $authType,
                'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Website User Key'},
                'data'            => $data,
                'username'        => $this->auroraModelData->{'Website User Handle'},
                'email'           => $this->auroraModelData->{'Website User Handle'},
                'created_at'      => $this->parseDatetime($this->auroraModelData->{'Website User Created'}),
                'is_root'         => true,
                'fetched_at'      => now(),
                'last_fetched_at' => now(),
            ];

        if ($password) {
            data_set($this->parsedData, 'webUser.password', $password);
        }
    }

    protected function isSha256($hash): bool
    {
        if (preg_match("/^([a-f0-9]{64})$/", $hash) == 1) {
            return true;
        } else {
            return false;
        }
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Website User Dimension')
            ->where('Website User Key', $id)->first();
    }
}
