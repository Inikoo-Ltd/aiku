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
                'label'    => __('Master Department'),
                'route'     => [
                    'name'       => 'grp.masters.master_shops.show.master_departments.show',
                    'parameters' => [$masterDepartment->masterShop->slug, $masterDepartment->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Master Department')
                ]
            ],
            [
                'label'    => __('Master Sub-departments'),
                'number'   => $masterDepartment->stats->number_current_master_product_categories_type_sub_department,
                'route'     => [
                    'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.index',
                    'parameters' => [$masterDepartment->masterShop->slug, $masterDepartment->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-dot-circle'],
                    'tooltip' => __('Master sub-departments')
                ]
            ],
            [
                 'label'    => __('Master Families'),
                 'number'   => $masterDepartment->stats->number_current_master_product_categories_type_family,
                 'route'     => [
                     'name'       => 'grp.masters.master_shops.show.master_departments.show.master_families.index',
                     'parameters' => [$masterDepartment->masterShop->slug, $masterDepartment->slug]
                 ],
                 'leftIcon' => [
                     'icon'    => ['fal', 'fa-folder'],
                     'tooltip' => __('Master families')
                 ]
            ],
            [
                'label'    => __('Master Products'),
                'number'   => $masterDepartment->stats->number_current_products,
                'route'     => [
                    'name'       => 'grp.masters.master_shops.show.master_departments.show.master_products.index',
                    'parameters' => [$masterDepartment->masterShop->slug, $masterDepartment->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-cube'],
                    'tooltip' => __('Master products')
                ]
            ],
            [
                'label'    => __('Master Collections'),
                'number'   => 0,
                'route'     => [
                    'name'       => 'grp.masters.master_shops.show.master_departments.show.master_collections.index',
                    'parameters' => [$masterDepartment->masterShop->slug, $masterDepartment->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-album-collection'],
                    'tooltip' => __('master collections')
                ]
            ],


        ];
    }

}
