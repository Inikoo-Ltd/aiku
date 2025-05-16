<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): void
    {
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'name', Arr::get($modelData, 'name'));
        data_set($modelData, 'settings.credentials.consumer_key', Arr::get($modelData, 'consumer_key'));
        data_set($modelData, 'settings.credentials.consumer_secret', Arr::get($modelData, 'consumer_secret'));
        data_set($modelData, 'settings.credentials.store_url', Arr::get($modelData, 'store_url'));

        $customer->wooCommerceUser()->create($modelData);
    }
}
