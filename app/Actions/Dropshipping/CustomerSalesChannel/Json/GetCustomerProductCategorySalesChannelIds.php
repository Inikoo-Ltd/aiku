<?php

/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-10h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\Json;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class GetCustomerProductCategorySalesChannelIds extends OrgAction
{
    public function handle(Customer $customer, ProductCategory $productCategory): array
    {
        return GetRetinaCustomerProductCategorySalesChannelIds::run($customer, $productCategory);
    }

    public function asController(Customer $customer, ProductCategory $productCategory, ActionRequest $request)
    {
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $productCategory);
    }

}
