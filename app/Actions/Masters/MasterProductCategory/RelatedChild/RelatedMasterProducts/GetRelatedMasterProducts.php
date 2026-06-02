<?php

/*
 * author Louis Perez
 * created on 29-05-2026-11h-01m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\RelatedChild\RelatedMasterProducts;

use App\Actions\Masters\MasterProductCategory\UI\GetMasterProductCategoryRelatedAssets;
use App\Http\Resources\Masters\RelatedMasterProductsResource;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRelatedMasterProducts
{
    use AsObject;

    public function handle(MasterProductCategory $masterProductCategory)
    {
        return [
            'id'               => $masterProductCategory->id,
            'data'             => RelatedMasterProductsResource::collection(GetMasterProductCategoryRelatedAssets::run($masterProductCategory)),
            'editable'         => true,
            'sync_payload_key' => 'master_asset_ids',
            'route_sync_related_products' => [
                'name'       => 'grp.models.master_product_category.related_assets.sync',
                'parameters' => [
                    'masterProductCategory' => $masterProductCategory->id,
                ]
            ],
            'route_get_products'          => [
                'name'       => 'grp.masters.master_shops.show.master_products.index',
                'parameters' => [
                    'masterShop' => $masterProductCategory->masterShop->slug,
                ]
            ]
        ];
    }
}
