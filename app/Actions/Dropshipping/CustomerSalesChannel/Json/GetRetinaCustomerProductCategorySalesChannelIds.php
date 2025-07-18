<?php

/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-10h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\Json;

use App\Actions\OrgAction;
use App\Actions\RetinaAction;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Services\QueryBuilder;
use Lorisleiva\Actions\ActionRequest;

class GetRetinaCustomerProductCategorySalesChannelIds extends RetinaAction
{
    public function handle(Customer $customer, ProductCategory $productCategory): array
    {
        $productIds = $productCategory->getProducts()->pluck('id');

        $queryBuilder = QueryBuilder::for(CustomerSalesChannel::class);
        $queryBuilder->where('customer_sales_channels.customer_id', $customer->id)
            ->distinct();
        $queryBuilder->join('portfolios', function ($join) use ($productIds) {
            $join->on('customer_sales_channels.id', '=', 'portfolios.customer_sales_channel_id')
                ->where('portfolios.item_type', '=', 'Product')
                ->whereIn('portfolios.item_id', $productIds);
        });

        $queryBuilder
            ->select('customer_sales_channels.id')
            ->groupBy('customer_sales_channels.id')
            ->havingRaw('COUNT(DISTINCT portfolios.item_id) = ?', [count($productIds)]);

        return $queryBuilder->get()->pluck('id')->toArray();
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $productCategory);
    }

}
