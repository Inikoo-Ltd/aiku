<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\FamilyWebsiteOrderResource;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDepartmentFamiliesOrder
{
    use AsObject;

    public function handle(ProductCategory $department): array
    {
        $families = $this->getFamilies($department);

        $followsMaster = (bool) data_get($department->shop->settings, 'catalog.family_order_follow_master', true);

        return [
            'id'               => $department->id,
            'data'             => FamilyWebsiteOrderResource::collection($families),
            'editable'         => !$followsMaster,
            'follows_master'   => $followsMaster,
            'number_uncurated' => $families->whereNull('website_position')->count(),
            'payload_key'      => 'families',
            'shop_settings_route' => [
                'name'       => 'grp.org.shops.show.settings.edit',
                'parameters' => [
                    'organisation' => $department->organisation->slug,
                    'shop'         => $department->shop->slug,
                ]
            ],
            'route_save_order' => [
                'name'       => 'grp.models.product_category.families_order.update',
                'parameters' => [
                    'productCategory' => $department->id
                ]
            ],
        ];
    }

    /**
     * Every family hanging off the department, the ones under its sub departments included,
     * in the order the website family blocks will show them.
     *
     * @return Collection<int, ProductCategory>
     */
    public function getFamilies(ProductCategory $department): Collection
    {
        return ProductCategory::where('product_categories.department_id', $department->id)
            ->where('product_categories.shop_id', $department->shop_id)
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->whereIn('product_categories.state', [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING])
            ->with(['subDepartment', 'stats'])
            ->orderByRaw('product_categories.website_position ASC NULLS LAST')
            ->orderByRaw('product_categories.created_at DESC')
            ->get();
    }
}
