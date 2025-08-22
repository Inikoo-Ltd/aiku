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
        $routeFamily = [
            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.show',
            'parameters' => request()->route()->originalParameters()
        ];

        $routeProducts = [
            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.index',
            'parameters' => request()->route()->originalParameters()
        ];

        if (in_array(request()->route()->getName(), ["grp.masters.master_shops.show.master_families.show", "grp.masters.master_shops.show.master_families.master_products.index"])) {
            $routeFamily = [
                'name'       => 'grp.masters.master_shops.show.master_families.show',
                'parameters' => request()->route()->originalParameters()
            ];

            $routeProducts = [
                'name'       => 'grp.masters.master_shops.show.master_families.master_products.index',
                'parameters' => request()->route()->originalParameters()
            ];
    } elseif (in_array(request()->route()->getName(), ["grp.masters.master_shops.show.master_departments.show.master_families.show", "grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.index"])) {
            $routeFamily = [
                'name'       => 'grp.masters.master_shops.show.master_departments.show.master_families.show',
                'parameters' => request()->route()->originalParameters()
            ];

            $routeProducts = [
                'name'       => 'grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.index',
                'parameters' => request()->route()->originalParameters()
            ];
        }

        return [
            [
                'isAnchor'   => true,
                'label'    => __('Master Family'),
                'route'     => $routeFamily,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Department')
                ]
            ],
             [
                 'label'    => __('Master Products'),
                 'number'   => $masterFamily->stats->number_current_products,
                 'route'     => $routeProducts,
                 'leftIcon' => [
                     'icon'    => ['fal', 'fa-cube'],
                     'tooltip' => __('products')
                 ]
             ],
        ];
    }
}
