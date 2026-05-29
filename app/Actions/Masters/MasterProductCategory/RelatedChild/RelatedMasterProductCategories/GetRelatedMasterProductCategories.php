<?php

/*
 * author Louis Perez
 * created on 29-05-2026-11h-01m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\RelatedChild\RelatedMasterProductCategories;

use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRelatedMasterProductCategories
{
    use AsObject;
    
    public function handle(MasterProductCategory $masterProductCategory)
    {
        $masterShop = $masterProductCategory->masterShop;

        return [
            'id'               => $masterProductCategory->id,
            'data'             => [],
            'editable'         => true,
            'sync_payload_key' => 'related_master_product_category_id',
            'route_sync_related_products' => [
                'name'       => 'grp.models.master_product_category.related_master_product_categories.sync',
                'parameters' => [
                    'masterProductCategory' => $masterProductCategory->id
                ]
            ],
            'route_get_department'        => [
                'name'       => 'grp.masters.master_shops.show.master_departments.index',
                'parameters' => [
                    'masterShop' => $masterShop->slug,
                ]
            ],
            'route_get_sub_department'    => [
                'name'       => 'grp.masters.master_shops.show.master_sub_departments.index',
                'parameters' => [
                    'masterShop' => $masterShop->slug,
                ]
            ],
            'route_get_family'            => [
                'name'       => 'grp.masters.master_shops.show.master_families.index',
                'parameters' => [
                    'masterShop' => $masterShop->slug,
                ]
            ]
        ];
    }
}
