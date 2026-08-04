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
        $salesRoute   = [];

        if ($parent instanceof Organisation) {
            $familyRoute  = [
                'name'       => 'grp.org.shops.show.catalogue.families.show',
                'parameters' => $routeParameters
            ];
            $productRoute = [
                'name'       => 'grp.org.shops.show.catalogue.families.show.products.index',
                'parameters' => [$parent->slug, $family->shop->slug, $family->slug]
            ];
            $salesRoute   = [
                'name'       => 'grp.org.shops.show.catalogue.families.show.products.sales',
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
            $salesRoute   = [
                'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show.products.sales',
                'parameters' => $routeParameters
            ];

        } elseif ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            if (request()->route()->getName() == 'grp.org.shops.show.catalogue.sub_departments.show.families.show' ||
               request()->route()->getName() == 'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.index' ||
               request()->route()->getName() == 'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.sales') {
                $familyRoute  = [
                    'name'       => 'grp.org.shops.show.catalogue.sub_departments.show.families.show',
                    'parameters' => $routeParameters
                ];
                $productRoute = [
                    'name'       => 'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.index',
                    'parameters' => $routeParameters
                ];
                $salesRoute   = [
                    'name'       => 'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.sales',
                    'parameters' => $routeParameters
                ];
            } else {
                $familyRoute  = [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show',
                    'parameters' => $routeParameters
                ];
                $productRoute = [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.products.index',
                    'parameters' => $routeParameters
                ];
                $salesRoute   = [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.products.sales',
                    'parameters' => $routeParameters
                ];
            }
        } elseif ($parent->type == ProductCategoryTypeEnum::FAMILY) {
            if (in_array($request->route()->getName(), [
                'grp.org.shops.show.catalogue.departments.show.families.show.products.index',
                'grp.org.shops.show.catalogue.departments.show.families.show.products.sales',
            ])) {
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
            $familyRoute  = [
                'name'       => 'grp.org.shops.show.catalogue.families.show',
                'parameters' => $routeParameters
            ];
            $productRoute = [
                'name'       => 'grp.org.shops.show.catalogue.families.show.products.index',
                'parameters' => $routeParameters
            ];
        }

        return [
            [
                'isAnchor'   => true,
                'label'    => __('Family'),
                'route'     => $familyRoute,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Family')
                ]
            ],

            [
                'label'    => __('Products'),
                'number'   => $family->stats->number_products,
                'route'     => $productRoute,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-cube'],
                    'tooltip' => __('products')
                ]
            ],




        ];
    }

}
