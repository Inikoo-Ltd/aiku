<?php

/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-10h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\Json;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Services\QueryBuilder;
use Lorisleiva\Actions\ActionRequest;

class GetCustomerProductSalesChannelIds extends OrgAction
{
    public function handle(Customer $customer, Product $product): array
    {
        $queryBuilder = QueryBuilder::for(CustomerSalesChannel::class);
        $queryBuilder->where('customer_sales_channels.customer_id', $customer->id);
        $queryBuilder->join('portfolios', function ($join) use ($product) {
            $join->on('customer_sales_channels.id', '=', 'portfolios.customer_sales_channel_id')
                ->where('portfolios.item_type', '=', 'Product')
                ->where('portfolios.item_id', '=', $product->id);
        });

        $queryBuilder
            ->select('customer_sales_channels.id');

        return $queryBuilder->get()->pluck('id')->toArray();
    }

    public function asController(Customer $customer, Product $product, ActionRequest $request)
    {
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $product);
    }

}
