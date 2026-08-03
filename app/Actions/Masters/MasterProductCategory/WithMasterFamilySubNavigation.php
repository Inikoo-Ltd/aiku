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

        $routeFamilies = [
            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.families',
            'parameters' => request()->route()->originalParameters()
        ];

        $routeProducts = [
            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.index',
            'parameters' => array_merge(
                request()->route()->originalParameters(),
                [
                    'index_elements[status]' => 'active'
                ]
            )
        ];

        $routeSales = [
            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.sales',
            'parameters' => request()->route()->originalParameters()
        ];

        if (in_array(request()->route()->getName(), ["grp.masters.master_shops.show.master_families.show", "grp.masters.master_shops.show.master_families.families", "grp.masters.master_shops.show.master_families.master_products.index", "grp.masters.master_shops.show.master_families.master_products.index.filter_in_variant", "grp.masters.master_shops.show.master_families.master_products.sales"])) {
            $routeFamily = [
                'name'       => 'grp.masters.master_shops.show.master_families.show',
                'parameters' => request()->route()->originalParameters()
            ];

            $routeFamilies = [
                'name'       => 'grp.masters.master_shops.show.master_families.families',
                'parameters' => request()->route()->originalParameters()
            ];

            $routeProducts = [
                'name'       => 'grp.masters.master_shops.show.master_families.master_products.index',
                'parameters' => array_merge(
                    request()->route()->originalParameters(),
                    [
                        'index_elements[status]' => 'active'
                    ]
                )
            ];

            $routeSales = [
                'name'       => 'grp.masters.master_shops.show.master_families.master_products.sales',
                'parameters' => request()->route()->originalParameters()
            ];
        } elseif (in_array(request()->route()->getName(), ["grp.masters.master_shops.show.master_departments.show.master_families.show", "grp.masters.master_shops.show.master_departments.show.master_families.families", "grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.index", "grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.sales"])) {
            $routeFamily = [
                'name'       => 'grp.masters.master_shops.show.master_departments.show.master_families.show',
                'parameters' => request()->route()->originalParameters()
            ];

            $routeFamilies = [
                'name'       => 'grp.masters.master_shops.show.master_departments.show.master_families.families',
                'parameters' => request()->route()->originalParameters()
            ];

            $routeProducts = [
                'name'       => 'grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.index',
                'parameters' => array_merge(
                    request()->route()->originalParameters(),
                    [
                        'index_elements[status]' => 'active'
                    ]
                )
            ];

            $routeSales = [
                'name'       => 'grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.sales',
                'parameters' => request()->route()->originalParameters()
            ];
        } elseif (in_array(request()->route()->getName(), ["grp.masters.master_shops.show.master_sub_departments.master_families.show", "grp.masters.master_shops.show.master_sub_departments.master_families.families", "grp.masters.master_shops.show.master_sub_departments.master_families.master_products.index", "grp.masters.master_shops.show.master_sub_departments.master_families.master_products.sales"])) {
            $routeFamily = [
                'name'       => 'grp.masters.master_shops.show.master_sub_departments.master_families.show',
                'parameters' => request()->route()->originalParameters()
            ];

            $routeFamilies = [
                'name'       => 'grp.masters.master_shops.show.master_sub_departments.master_families.families',
                'parameters' => request()->route()->originalParameters()
            ];

            $routeProducts = [
                'name'       => 'grp.masters.master_shops.show.master_sub_departments.master_families.master_products.index',
                'parameters' => array_merge(
                    request()->route()->originalParameters(),
                    [
                        'index_elements[status]' => 'active'
                    ]
                )
            ];

            $routeSales = [
                'name'       => 'grp.masters.master_shops.show.master_sub_departments.master_families.master_products.sales',
                'parameters' => request()->route()->originalParameters()
            ];
        } elseif (in_array(request()->route()->getName(), ["grp.masters.master_shops.show.master_family.mismatch_detected.show", "grp.masters.master_shops.show.master_family.mismatch_detected.families", "grp.masters.master_shops.show.master_family.mismatch_detected.master_products.index", "grp.masters.master_shops.show.master_family.mismatch_detected.master_products.sales"])) {
            $routeFamily = [
                'name'       => 'grp.masters.master_shops.show.master_family.mismatch_detected.show',
                'parameters' => request()->route()->originalParameters()
            ];

            $routeFamilies = [
                'name'       => 'grp.masters.master_shops.show.master_family.mismatch_detected.families',
                'parameters' => request()->route()->originalParameters()
            ];

            $routeProducts = [
                'name'       => 'grp.masters.master_shops.show.master_family.mismatch_detected.master_products.index',
                'parameters' => array_merge(
                    request()->route()->originalParameters(),
                    [
                        'index_elements[status]' => 'active'
                    ]
                )
            ];

            $routeSales = [
                'name'       => 'grp.masters.master_shops.show.master_family.mismatch_detected.master_products.sales',
                'parameters' => request()->route()->originalParameters()
            ];
        }

        return [
            [
                'isAnchor' => true,
                'label'    => __('Master Family'),
                'route'    => $routeFamily,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Family')
                ]
            ],
            [
                'label'    => __('Families in Shop'),
                'number'   => $masterFamily->stats->number_current_families,
                'route'     => $routeFamilies,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-store'],
                    'tooltip' => __('Families in Shop')
                ]
            ],
            [
                'label'    => __('Master Products'),
                'number'   => $masterFamily->stats->number_current_master_assets,
                'route'    => $routeProducts,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-cube'],
                    'tooltip' => __('Products')
                ]
            ],
            [
                'label'    => __('Sales'),
                'route'    => $routeSales,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-money-bill-wave'],
                    'tooltip' => __('Master products sales')
                ]
            ],
        ];
    }
}
