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
                    'name'       => 'grp.masters.master_shops.show',
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
                'label'    => __('M. Departments'),
                'tooltip'    => __('Master Departments'),
                'route'    => [
                    'name'       => 'grp.masters.master_shops.show.master_departments.index',
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
                'number'   => $masterShop->stats->number_current_master_product_categories_type_sub_department,
                'label'    => __('M. Sub-departments'),
                'tooltip'  => __('Master Sub-departments'),
                'route'    => [
                    'name'       => 'grp.masters.master_shops.show.master_sub_departments.index',
                    'parameters' => [
                        'masterShop' => $masterShop->slug
                    ]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Master Sub Departments')
                ]
            ],
            [
                'number'   => $masterShop->stats->number_current_master_product_categories_type_family,
                'label'    => __('M. Families'),
                'tooltip'    => __('Master Families'),
                'route'    => [
                    'name'       => 'grp.masters.master_shops.show.master_families.index',
                    'parameters' => [
                        'masterShop' => $masterShop->slug,
                        '_query' => [
                            'elements[status]' => 'active'
                        ]
                    ]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Master Families')
                ]
            ],
            [
                'number'   => $masterShop->stats->number_current_master_assets_type_product,
                'label'    => __('M. Products'),
                'tooltip'    => __('Master Products'),
                'route'    => [
                    'name'       => 'grp.masters.master_shops.show.master_products.index',
                    'parameters' => [
                        'masterShop' => $masterShop->slug,
                        '_query' => [
                            'elements[status]' => 'active'
                        ]
                    ]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Master Products')
                ]
            ],
            [
                'number'   => $masterShop->stats->number_master_collections,
                'label'    => __('M. Collections'),
                'tooltip'    => __('Master Collections'),
                'route'    => [
                    'name'       => 'grp.masters.master_shops.show.master_collections.index',
                    'parameters' => [
                        'masterShop' => $masterShop->slug
                    ]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Master Collections')
                ]
            ],
            app()->environment('local') ? [
                'number'   => $masterShop->stats->number_master_families_with_vol_gr_offer,
                'label'    => __('M. Vol/GR'),
                'tooltip'    => __('Master Volume/Gold Reward Discount'),
                'route'    => [
                    'name'       => 'grp.masters.master_shops.show.master_gr.index',
                    'parameters' => [
                        'masterShop' => $masterShop->slug
                    ]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-medal'],
                    'tooltip' => __('Gold Reward')
                ],
                'align'    => 'right',
            ] : [],
        ];
    }
}
