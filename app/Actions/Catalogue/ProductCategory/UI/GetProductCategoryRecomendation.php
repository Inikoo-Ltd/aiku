<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\Product\UI\IndexProducts;
use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GetProductCategoryRecomendation extends OrgAction
{

    public function handle(ProductCategory $productCategory): LengthAwarePaginator
    {
        $productCategory->refresh();

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->leftJoin('shops', 'products.shop_id', 'shops.id');
        $queryBuilder->leftJoin('currencies', 'currencies.id', 'shops.currency_id');
        $queryBuilder->leftJoin('organisations', 'products.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('variants as variant', 'variant.id', '=', 'products.variant_id');
        $queryBuilder->where('products.is_main', true);
        $queryBuilder->whereNull('products.exclusive_for_customer_id');
        $queryBuilder->leftJoin('assets', 'products.asset_id', 'assets.id');
        $queryBuilder->whereIn('products.id', $productCategory->relatedProducts->pluck('id'));
        $queryBuilder->leftJoin('product_category_has_related_products', function ($join) use ($productCategory) {
            $join->on('products.id', '=', 'product_category_has_related_products.product_id')
                ->where('product_category_has_related_products.product_category_id', '=', $productCategory->id);
        });

        $selects = [
            'products.id',
            'products.code',
            'products.name',
            'products.state',
            'products.price',
            'products.rrp',
            'products.unit',
            'products.is_for_sale',
            'products.created_at',
            'products.updated_at',
            'products.slug',
            'products.asset_id',
            'products.available_quantity',
            'products.units',
            'products.web_images',
            DB::raw('products.price / products.units as rrp_per_unit'),
            'currencies.code as currency_code',
            'variant.slug as variant_slug',
            'variant.code as variant_code',
            'products.is_variant_leader as is_variant_leader',
            'products.master_product_id',
            'assets.health_rank',
            'product_category_has_related_products.position'
        ];

        $queryBuilder
            ->with('orgStocks')
            ->select($selects)
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id')
            ->orderBy('product_category_has_related_products.position', 'asc');

        return $queryBuilder
                ->withPaginator(null, tableName: request()->route()->getName())
                ->withQueryString();
    }
}
