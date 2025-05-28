<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 14:05:15 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Models\Catalogue\ProductCategory;

trait WithDepartmentSubNavigation
{
    protected function getDepartmentSubNavigation(ProductCategory $department): array
    {
        return [
            [
                'isAnchor'   => true,
                'label'    => __('Department'),
                'route'     => [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show',
                    'parameters' => [$this->organisation->slug, $department->shop->slug, $department->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Department')
                ]
            ],
            [
                'label'    => __('Sub-departments'),
                'number'   => $department->stats->number_sub_departments,
                'route'     => [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.index',
                    'parameters' => [$this->organisation->slug, $department->shop->slug, $department->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-dot-circle'],
                    'tooltip' => __('sub-departments')
                ]
            ],
            [
                'label'    => __('Families'),
                'number'   => $department->stats->number_current_families,
                'route'     => [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.families.index',
                    'parameters' => [$this->organisation->slug, $department->shop->slug, $department->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-folder'],
                    'tooltip' => __('families')
                ]
            ],
            [
                'label'    => __('Products'),
                'number'   => $department->stats->number_current_products,
                'route'     => [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.products.index',
                    'parameters' => [$this->organisation->slug, $department->shop->slug, $department->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-cube'],
                    'tooltip' => __('products')
                ]
            ],
            [
                'label'    => __('Collections'),
                'number'   => $department->stats->number_collections,
                'route'     => [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.collection.index',
                    'parameters' => [$this->organisation->slug, $department->shop->slug, $department->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-album-collection'],
                    'tooltip' => __('collections')
                ]
            ],
        ];
    }

}
