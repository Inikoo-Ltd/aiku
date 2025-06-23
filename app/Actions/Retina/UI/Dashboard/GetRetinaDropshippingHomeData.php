<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\UI\Dashboard;

use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\CRM\CustomerSalesChannelsResource;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRetinaDropshippingHomeData
{
    use AsObject;

    public function handle(Customer $customer): array
    {
        return [
            'customer' => CustomerResource::make($customer)->getArray(),
            'channels' => CustomerSalesChannelsResource::collection($customer->customerSalesChannels)
        ];
    }
}
