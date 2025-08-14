<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;

trait WithMasterSubDepartmentSubNavigation
{
    protected function getMasterSubDepartmentSubNavigation(MasterProductCategory $masterSubDepartment): array
    {
        $subRoute = [
            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.show',
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

        if ($this->parent instanceof MasterShop || ($this->parent instanceof MasterProductCategory && $this->parent->type === MasterProductCategoryTypeEnum::SUB_DEPARTMENT)) {
            $subRoute = [
                'name'       => 'grp.masters.master_shops.show.master_sub_departments.show',
                'parameters' => request()->route()->originalParameters(),
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
