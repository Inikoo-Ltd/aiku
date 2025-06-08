<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Jun 2025 16:11:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Basket\UI;

use App\Actions\RetinaAction;
use App\Http\Resources\Dropshipping\SelectProductsForBasketResource;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaDropshippingProductsForBasket extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customer_clients.name', $value)
                    ->orWhereStartWith('customer_clients.email', $value)
                    ->orWhere('customer_clients.reference', '=', $value);
            });
        });


        $unUploadedFilter = AllowedFilter::callback('un_upload', function ($query) {
            $query->whereNull('platform_product_id');
        });

        $query = QueryBuilder::for(Product::class);
        $query->where('products.shop_id', $this->shop->id);

        $query->leftJoin('portfolios', function ($join) use ($customerSalesChannel) {
            $join->on('portfolios.item_id', '=', 'products.id')
                ->where('portfolios.item_type', '=', 'Product')
                ->where('portfolios.customer_sales_channel_id', '=', $customerSalesChannel->id);
        });


        if ($this->customer->number_exclusive_products > 0) {
            $query->where(function ($query) {
                $query->where('products.is_for_sale', true)
                    ->orWhere('products.exclusive_for_customer_id', $this->customer->id);
            });
        } else {
            $query->whereIn('products.is_for_sale', true);

        }


        $query->select([
            'products.id',
            'products.code',
            'products.name',
            'products.price',
            'products.available_quantity',
            'products.image_id',

        ]);


        return $query->defaultSort('products.code')
            ->allowedFilters([$unUploadedFilter, $globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {

        $this->initialisation($request);

        return $this->handle($customerSalesChannel, 'products');
    }

    public function jsonResponse(LengthAwarePaginator $productsForBasket): \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\Resources\Json\JsonResource
    {
        return SelectProductsForBasketResource::collection($productsForBasket);
    }


}
