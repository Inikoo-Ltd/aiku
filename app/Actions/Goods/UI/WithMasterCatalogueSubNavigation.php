<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:14:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\UI;

use App\Models\Masters\MasterShop;

trait WithMasterCatalogueSubNavigation
{
    protected function getMasterShopNavigation(MasterShop $masterShop): array
    {
        return [
            [
                'isAnchor' => true,
                'label'    => __($masterShop->name),

                'route'    => [
                    'name'       => 'grp.masters.shops.show',
                    'parameters' => [
                        'masterShop' => $masterShop->slug
                    ]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-store-alt'],
                    'tooltip' => __('Master Shop')
                ]


            ],
            [
                'number'   => $masterShop->stats->number_current_master_product_categories_type_department,
                'label'    => __('Departments'),
                'route'    => [
                    'name'       => 'grp.masters.shops.show.departments.index',
                    'parameters' => [
                        'masterShop' => $masterShop->slug
                    ]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Master Departments')
                ]
            ],

            [
                'number'   => $masterShop->stats->number_current_master_product_categories_type_family,
                'label'    => __('Families'),
                'route'    => [
                    'name'       => 'grp.masters.shops.show.families.index',
                    'parameters' => [
                        'masterShop' => $masterShop->slug
                    ]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Master Families')
                ]
            ],

            [
                'number'   => $masterShop->stats->number_current_master_assets_type_product,
                'label'    => __('Products'),
                'route'    => [
                    'name'       => 'grp.masters.shops.show.products.index',
                    'parameters' => [
                        'masterShop' => $masterShop->slug
                    ]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Master Products')
                ]
            ],
            [
                'align'    => 'right',
                'number'   => $masterShop->stats->number_current_master_product_categories_type_sub_department,
                'label'    => __('Subs'),
                'tooltip'  => __('Sub Departments'),
                'route'    => [
                    'name'       => 'grp.masters.shops.show.sub-departments.index',
                    'parameters' => [
                        'masterShop' => $masterShop->slug
                    ]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Master Sub Departments')
                ]
            ],

        ];
    }

}
