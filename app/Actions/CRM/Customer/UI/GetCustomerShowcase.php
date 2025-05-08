<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Http\Resources\CRM\CustomersResource;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomerShowcase
{
    use AsObject;

    public function handle(Customer $customer): array
    {
        $webUser = $customer->webUsers()->first();
        $webUserRoute = null;
        if ($webUser) {
            $webUserRoute = [
                'name'       => 'grp.org.shops.show.crm.customers.show.web-users.edit',
                'parameters' => [
                    'organisation' => $customer->organisation->slug,
                    'shop'         => $customer->shop->slug,
                    'customer'     => $customer->slug,
                    'webUser'      => $webUser->slug
                ]
            ];
        }


        return [
            'customer' => CustomersResource::make($customer)->getArray(),
            'address_management' => GetCustomerAddressManagement::run(customer:$customer),
            'require_approval' => Arr::get($customer->shop->settings, 'registration.require_approval', false),
            'approveRoute'       => [
                'name'       => 'grp.models.customer.approve',
                'parameters' => [
                    'customer' => $customer->id
                ]
            ],
            'editWebUser' => $webUserRoute
        ];
    }
}
