<?php

/*
 * author Louis Perez
 * created on 29-05-2026-11h-01m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\RelatedChild\RelatedProducts;

use App\Actions\Catalogue\ProductCategory\UI\GetProductCategoryRecomendation;
use App\Http\Resources\Masters\RelatedMasterProductsResource;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRelatedProducts
{
    use AsObject;

    public function handle(ProductCategory $productCategory)
    {
        $shop         = $productCategory->shop;
        $organisation = $productCategory->organisation;
        $isRelatedProductFollowMaster = (bool) data_get($shop->settings, 'catalog.related_product_follow_master', false);

        return [
            'id'        => $productCategory->id,
            'data'      => RelatedMasterProductsResource::collection(GetProductCategoryRecomendation::run($productCategory, $isRelatedProductFollowMaster)),
            'editable'  => !$isRelatedProductFollowMaster,
            'route_sync_related_products' => [
                'name' => 'grp.models.product_category.related_products.sync',
                'parameters' => [
                    'productCategory' => $productCategory->id,
                ]
            ],
            'sync_payload_key' => 'product_ids',
            'route_get_products' => [
                'name' => 'grp.org.shops.show.catalogue.products.current_products.index',
                'parameters' => [
                    'organisation' => $organisation->slug,
                    'shop' => $shop->slug,
                ]
            ]
        ];
    }
}
