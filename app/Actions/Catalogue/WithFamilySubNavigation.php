<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 14:05:15 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

trait WithFamilySubNavigation
{
    protected function getFamilySubNavigation(ProductCategory $family, Organisation|ProductCategory|Shop $parent, ActionRequest $request): array
    {
        $routeParameters = $request->route()->originalParameters();

        $productRoute = [];
        $familyRoute  = [];
        if ($parent instanceof Organisation) {
            $familyRoute  = [
                'name'       => 'grp.org.shops.show.catalogue.families.show',
                'parameters' => $routeParameters
            ];
            $productRoute = [
                'name'       => 'grp.org.shops.show.catalogue.families.show.products.index',
                'parameters' => [$parent->slug, $family->shop->slug, $family->slug]
            ];
        } elseif ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $familyRoute  = [
                'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show',
                'parameters' => $routeParameters
            ];
            $productRoute = [
                'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show.products.index',
                'parameters' => $routeParameters
            ];
        } elseif ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $productRoute = [
                'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.products.index',
                'parameters' => $routeParameters
            ];
        } elseif ($parent->type == ProductCategoryTypeEnum::FAMILY) {
            if ($request->route()->getName() == 'grp.org.shops.show.catalogue.departments.show.families.show.products.index') {
                $familyRoute  = [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show',
                    'parameters' => $routeParameters
                ];
                $productRoute = [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show.products.index',
                    'parameters' => $routeParameters
                ];
            } else {
                $familyRoute  = [
                    'name'       => 'grp.org.shops.show.catalogue.families.show',
                    'parameters' => $routeParameters
                ];
                $productRoute = [
                    'name'       => 'grp.org.shops.show.catalogue.families.show.products.index',
                    'parameters' => $routeParameters
                ];
            }
        } elseif ($parent instanceof Shop) {
            $productRoute = [
                'name'       => 'grp.org.shops.show.catalogue.families.show.products.index',
                'parameters' => $routeParameters
            ];
        }


        return [
            [
                'isAnchor'   => true,
                'label'    => __('Family'),
                'href'     => $familyRoute,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('family')
                ]
            ],

            [
                'label'    => __('Products'),
                'number'   => $family->stats->number_products,
                'href'     => $productRoute,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-cube'],
                    'tooltip' => __('products')
                ]
            ],
        ];
    }

}
