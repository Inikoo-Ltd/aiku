<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Http\Resources\CRM\CustomersResource;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomerShowcase
{
    use AsObject;

    public function handle(Customer $customer): array
    {

        return [
            'customer' => CustomersResource::make($customer)->getArray(),
            'address_management' => GetCustomerAddressManagement::run(customer:$customer),
            'approveRoute'       => [
                'name'       => 'grp.models.customer.approve',
                'parameters' => [
                    'customer' => $customer->id
                ]
            ],
        ];
    }
}
