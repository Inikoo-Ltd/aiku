<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 14:05:15 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Models\Catalogue\ProductCategory;

trait WithSubDepartmentSubNavigation
{
    protected function getSubDepartmentSubNavigation(ProductCategory $subDepartment): array
    {
        $route = request()->route()->getName();

        $subDepartmentRoute = [
            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
            'parameters' => [$this->organisation->slug, $subDepartment->shop->slug, $subDepartment->parent->slug, $subDepartment->slug]
        ];
        $familiesRoute = [
            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.index',
            'parameters' => [$this->organisation->slug, $this->shop->slug, $subDepartment->department->slug, $subDepartment->slug]
        ];
        $productsRoute = [
             'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.product.index',
            'parameters' => [$this->organisation->slug, $this->shop->slug, $subDepartment->department->slug, $subDepartment->slug]
        ];
        $collectionsRoute = [
            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index',
            'parameters' => [$this->organisation->slug, $this->shop->slug, $subDepartment->department->slug, $subDepartment->slug]
        ];

        $targetRoutes = [
            'grp.org.shops.show.catalogue.sub_departments.show',
            'grp.org.shops.show.catalogue.sub_departments.show.families.index',
            'grp.org.shops.show.catalogue.sub_departments.show.products.index',
            'grp.org.shops.show.catalogue.sub_departments.show.collection.index',
        ];

        if (in_array($route, $targetRoutes, true)) {
            $subDepartmentRoute = [
                'name'       => 'grp.org.shops.show.catalogue.sub_departments.show',
                'parameters' => [$this->organisation->slug, $subDepartment->shop->slug, $subDepartment->slug]
            ];
            $familiesRoute = [
                'name'       => 'grp.org.shops.show.catalogue.sub_departments.show.families.index',
                'parameters' => [$this->organisation->slug, $subDepartment->shop->slug, $subDepartment->slug]
            ];
            $productsRoute = [
                'name'       => 'grp.org.shops.show.catalogue.sub_departments.show.products.index',
                'parameters' => [$this->organisation->slug, $subDepartment->shop->slug, $subDepartment->slug]
            ];
            $collectionsRoute = [
                'name'       => 'grp.org.shops.show.catalogue.sub_departments.show.collection.index',
                'parameters' => [$this->organisation->slug, $subDepartment->shop->slug, $subDepartment->slug]
            ];
        }

        return [
            [
                'isAnchor'   => true,
                'label'    => __('Sub-department'),
                'route'     => $subDepartmentRoute,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Sub-department')
                ]
            ],
            [
                'label'    => __('Families'),
                'number'   => $subDepartment->stats->number_families,
                'route'     => $familiesRoute,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-folder'],
                    'tooltip' => __('families')
                ]
            ],
            [
                'label'    => __('Products'),
                'number'   => $subDepartment->stats->number_products,
                'route'     => $productsRoute,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-cube'],
                    'tooltip' => __('products')
                ]
            ],
             [
                 'label'    => __('Collections'),
                 'number'   => $subDepartment->stats->number_collections,
                 'route'     => $collectionsRoute,
                 'leftIcon' => [
                     'icon'    => ['fal', 'fa-album-collection'],
                     'tooltip' => __('collections')
                 ]
             ],
        ];
    }

}
