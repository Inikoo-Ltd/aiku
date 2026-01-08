<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\RetinaAction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetRetinaSalesChannelInProductCategory extends RetinaAction
{
    public function handle(Customer $customer, ProductCategory $productCategory): array
    {
        $collectedSalesChannels = [];
        $products = $productCategory->getProducts();

        foreach ($customer->customerSalesChannels as $customerSalesChannel) {
            $currentPortfolio = DB::table('portfolios')
                ->where('customer_id', $customer->id)
                ->where('item_type', 'Product')
                ->where('customer_sales_channel_id', $customerSalesChannel->id)
                ->whereIn('item_id', $products->pluck('id'))
                ->count();

            if ($currentPortfolio === $products->count()) {
                $collectedSalesChannels[] = $customerSalesChannel->id;
            }
        }

        return $collectedSalesChannels;
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle(customer: $this->customer, productCategory: $productCategory);
    }

    public function jsonResponse($customerSalesChannels): array
    {
        return $customerSalesChannels;
    }
}
