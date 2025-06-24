<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-11h-58m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Group\Customer;

use App\Actions\OrgAction;
use App\Http\Resources\Api\CustomerApiResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class ShowApiCustomer extends OrgAction
{
    public function handle(Customer $customer): Customer
    {
        return $customer;
    }

    public function jsonResponse(Customer $customer): \Illuminate\Http\Resources\Json\JsonResource|CustomerApiResource
    {
        return CustomerApiResource::make($customer);
    }

    public function asController(Shop $shop, Customer $customer, ActionRequest $request): Customer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customer);
    }
}
