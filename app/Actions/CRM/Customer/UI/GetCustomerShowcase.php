<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomerShowcase
{
    use AsObject;

    public function handle(Customer $customer): array
    {

        return [
            'customer' => CustomersResource::make($customer)->getArray(),
            'address_management' => GetCustomerAddressManagement::run(customer:$customer),
        ];
    }
}
