<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory;

use App\Models\Masters\MasterProductCategory;

trait WithMasterFamilySubNavigation
{
    protected function getMasterFamilySubNavigation(MasterProductCategory $masterFamily): array
    {
        return [
            [
                'isAnchor'   => true,
                'label'    => __('Master Family'),
                'route'     => [
                    'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.show',
                    'parameters' => [$masterFamily->masterShop->slug, $masterFamily->masterDepartment->slug, $masterFamily->masterSubDepartment->slug, $masterFamily->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Department')
                ]
            ],
             [
                 'label'    => __('Master Products'),
                 'number'   => $masterFamily->stats->number_current_products,
                 'route'     => [
                     'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.index',
                     'parameters' => [$masterFamily->masterShop->slug, $masterFamily->masterDepartment->slug, $masterFamily->masterSubDepartment->slug, $masterFamily->slug]
                 ],
                 'leftIcon' => [
                     'icon'    => ['fal', 'fa-cube'],
                     'tooltip' => __('products')
                 ]
             ],
        ];
    }
}
