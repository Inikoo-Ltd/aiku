<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory;

use App\Models\Masters\MasterProductCategory;

trait WithMasterDepartmentSubNavigation
{
    protected function getMasterDepartmentSubNavigation(MasterProductCategory $masterDepartment): array
    {
        return [
            [
                'isAnchor'   => true,
                'label'    => __('Department'),
                'route'     => [
                    'name'       => 'grp.masters.master_departments.show',
                    'parameters' => [$masterDepartment->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Department')
                ]
            ],
            [
                'label'    => __('Sub-departments'),
                'number'   => $masterDepartment->stats->number_sub_departments,
                'route'     => [
                    'name'       => 'grp.masters.master_departments.show.master_sub_departments.index',
                    'parameters' => [$masterDepartment->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-dot-circle'],
                    'tooltip' => __('sub-departments')
                ]
            ],
             [
                 'label'    => __('Families'),
                 'number'   => $masterDepartment->stats->number_current_families,
                 'route'     => [
                     'name'       => 'grp.masters.master_departments.show.master_families.index',
                     'parameters' => [$masterDepartment->slug]
                 ],
                 'leftIcon' => [
                     'icon'    => ['fal', 'fa-folder'],
                     'tooltip' => __('families')
                 ]
             ],
            // [
            //     'label'    => __('Collections'),
            //     'number'   => 0,
            //     'route'     => [
            //         'name'       => 'grp.org.shops.show.catalogue.departments.show.collection.index',
            //         'parameters' => [$this->organisation->slug, $masterDepartment->shop->slug, $masterDepartment->slug]
            //     ],
            //     'leftIcon' => [
            //         'icon'    => ['fal', 'fa-album-collection'],
            //         'tooltip' => __('collections')
            //     ]
            // ],
            // [
            //     'label'    => __('Products'),
            //     'number'   => $masterDepartment->stats->number_current_products,
            //     'route'     => [
            //         'name'       => 'grp.org.shops.show.catalogue.departments.show.products.index',
            //         'parameters' => [$this->organisation->slug, $masterDepartment->shop->slug, $masterDepartment->slug]
            //     ],
            //     'leftIcon' => [
            //         'icon'    => ['fal', 'fa-cube'],
            //         'tooltip' => __('products')
            //     ]
            // ],

        ];
    }

}
