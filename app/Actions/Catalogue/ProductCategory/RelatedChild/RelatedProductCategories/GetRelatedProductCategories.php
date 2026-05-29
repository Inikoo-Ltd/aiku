<?php

/*
 * author Louis Perez
 * created on 29-05-2026-11h-01m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\RelatedChild\RelatedProductCategories;

use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRelatedProductCategories
{
    use AsObject;

    public function handle(ProductCategory $productCategory)
    {
        $shop         = $productCategory->shop;
        $organisation = $productCategory->organisation;
        $isRelatedProductCategoryFollowMaster = (bool) data_get($shop->settings, 'catalog.related_product_categories_follow_master', false);

        return [
            'id'               => $productCategory->id,
            'data'             => [],
            'editable'         => $isRelatedProductCategoryFollowMaster,
            'sync_payload_key' => 'product_categories_id',
            'route_sync_related_products' => [
                'name'       => 'grp.models.product_category.related_product_categories.sync',
                'parameters' => [
                    'productCategory' => $productCategory->id
                ]
            ],
            'route_get_department'        => [
                'name'       => 'grp.org.shops.show.catalogue.departments.index',
                'parameters' => [
                    'shop'         => $shop->slug,
                    'organisation' => $organisation->slug
                ]
            ],
            'route_get_sub_department'    => [
                'name'       => 'grp.org.shops.show.catalogue.sub_departments.index',
                'parameters' => [
                    'shop'         => $shop->slug,
                    'organisation' => $organisation->slug
                ]
            ],
            'route_get_family'            => [
                'name'       => 'grp.org.shops.show.catalogue.families.index',
                'parameters' => [
                    'shop'         => $shop->slug,
                    'organisation' => $organisation->slug
                ]
            ]
        ];
    }
}
