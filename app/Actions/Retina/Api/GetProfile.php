<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-13-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Api;

use App\Http\Resources\CRM\CustomerResource;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProfile
{
    use AsAction;

    public function asController(ActionRequest $request): Customer
    {
        $customer = $request->user();

        return $customer;
    }

    public function jsonResponse(Customer $customer): CustomerResource
    {
        return CustomerResource::make($customer);
    }

}
