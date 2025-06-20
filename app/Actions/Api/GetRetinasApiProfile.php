<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-13-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api;

use App\Http\Resources\Api\CustomersResource;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRetinasApiProfile
{
    use AsAction;

    public function asController(ActionRequest $request): Customer
    {
        return $request->user();
    }

    public function jsonResponse(Customer $customer): CustomersResource
    {
        return CustomersResource::make($customer);
    }

}
