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

trait WithMasterSubDepartmentSubNavigation
{
    protected function getMasterSubDepartmentSubNavigation(MasterProductCategory $masterSubDepartment): array
    {
        $subRoute = [
            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.show',
            'parameters' => request()->route()->originalParameters()
        ];

        $routeSubDepartmentsInShop = [
            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.sub_departments',
            'parameters' => request()->route()->originalParameters()
        ];

        $routeFamilies = [
            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.index',
            'parameters' => request()->route()->originalParameters()
        ];

        $routeCollections = [
             'name'       => 'grp.masters.master_shops.show.master_sub_departments.master_collections.index',
             'parameters' => request()->route()->originalParameters()
        ];

        if (in_array(request()->route()->getName(), ['grp.masters.master_shops.show.master_sub_departments.show', 'grp.masters.master_shops.show.master_sub_departments.sub_departments', 'grp.masters.master_shops.show.master_sub_departments.master_families.index', 'grp.masters.master_shops.show.master_sub_departments.master_collections.index'])) {
            $subRoute = [
                'name'       => 'grp.masters.master_shops.show.master_sub_departments.show',
                'parameters' => request()->route()->originalParameters(),
            ];

            $routeSubDepartmentsInShop = [
                'name'       => 'grp.masters.master_shops.show.master_sub_departments.sub_departments',
                'parameters' => request()->route()->originalParameters()
            ];

            $routeFamilies = [
                'name'       => 'grp.masters.master_shops.show.master_sub_departments.master_families.index',
                'parameters' => request()->route()->originalParameters(),
            ];

            $routeCollections = [
                 'name'       => 'grp.masters.master_shops.show.master_sub_departments.master_collections.index',
                 'parameters' => request()->route()->originalParameters(),
            ];
        }

        return [
            [
                'isAnchor'   => true,
                'label'    => __('Master Sub-department'),
                'route'     => $subRoute,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Sub-department')
                ]
            ],
            [
                'label'    => __('Sub-Departments in Shop'),
                'number'   => $masterSubDepartment->stats->number_current_sub_departments,
                'route'     => $routeSubDepartmentsInShop,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-store'],
                    'tooltip' => __('Sub-Departments in shop')
                ]
            ],
            [
                'label'    => __('Master Families'),
                'number'   => $masterSubDepartment->stats->number_current_master_product_categories_type_family,
                'route'     => $routeFamilies,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-folder'],
                    'tooltip' => __('families')
                ]
            ],
            [
                'label'    => __('Master Collections'),
                'number'   => 0,
                'route'     => $routeCollections,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-album-collection'],
                    'tooltip' => __('collections')
                ]
            ],
        ];
    }

}
