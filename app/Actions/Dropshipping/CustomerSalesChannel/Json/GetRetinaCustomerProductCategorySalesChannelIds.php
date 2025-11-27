<?php

/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-10h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\Json;

use App\Actions\Dropshipping\CustomerSalesChannel\Json\Traits\WithCustomerSalesChannelPortfolioQuery;
use App\Actions\RetinaAction;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class GetRetinaCustomerProductCategorySalesChannelIds extends RetinaAction
{
    use WithCustomerSalesChannelPortfolioQuery;

    public function handle(Customer $customer, ProductCategory $productCategory): array
    {
        $productIds = $this->getProductIdsFromProductCategory($productCategory);

        return $this->buildCustomerSalesChannelIdsForProductIds($customer, $productIds);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $productCategory);
    }

}
