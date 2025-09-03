<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Feb 2024 22:34:23 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use Lorisleiva\Actions\Concerns\AsAction;

class GetRetinaEcomNavigation
{
    use AsAction;

    public function handle(): array
    {
        $groupNavigation = [];

        $groupNavigation['dashboard'] = [
            'label' => __('Dashboard'),
            'icon' => ['fal', 'fa-tachometer-alt'],
            'root' => 'retina.dashboard.',
            'route' => [
                'name' => 'retina.dashboard.show'
            ],
            'topMenu' => [

            ]
        ];

        $groupNavigation['catalogue'] = [
            'label' => __('Catalogue'),
            'icon' => ['fal', 'fa-books'],
            'root' => 'retina.catalogue.',
            'route' => [
                'name' => 'retina.catalogue.dashboard'
            ],
            'topMenu' => [
                'subSections' =>
                    [
                        [
                            'label' => __(''),
                            'icon'  => ['far', 'fa-books'],
                            'root'  => 'retina.catalogue.',
                            'route' => [
                                'name' => 'retina.catalogue.dashboard'
                            ]
                        ],
                        [
                            'label' => __('Departments'),
                            'icon'  => ['far', 'fa-folder-tree'],
                            'root'  => 'retina.catalogue.departments.',
                            'route' => [
                                'name' => 'retina.catalogue.departments.index'
                            ]
                        ],
                        [
                            'label' => __('Sub Departments'),
                            'icon'  => ['far', 'fa-dot-circle'],
                            'root'  => 'retina.catalogue.sub_departments.',
                            'route' => [
                                'name' => 'retina.catalogue.sub_departments.index'
                            ]
                        ],
                        [
                            'label' => __('Collections'),
                            'icon'  => ['far', 'fa-album-collection'],
                            'root'  => 'retina.catalogue.collections.',
                            'route' => [
                                'name' => 'retina.catalogue.collections.index'
                            ]
                        ],
                        [
                            'label' => __('Families'),
                            'icon'  => ['far', 'fa-folder'],
                            'root'  => 'retina.catalogue.families.',
                            'route' => [
                                'name' => 'retina.catalogue.families.index'
                            ]
                        ],
                        [
                            'label' => __('Products'),
                            'icon'  => ['far', 'fa-cube'],
                            'root'  => 'retina.catalogue.products.',
                            'route' => [
                                'name' => 'retina.catalogue.products.index'
                            ]
                        ],
                    ]
            ]
        ];

        $groupNavigation['basket'] = [
            'label' => __('Basket'),
            'icon' => ['fal', 'fa-shopping-cart'],
            'root' => 'retina.ecom.basket.',
            'route' => [
                'name' => 'retina.ecom.basket.show'
            ],
            'topMenu' => [

            ]
        ];

        $groupNavigation['orders'] = [
            'label'   => __('Orders'),
            'icon'    => ['fal', 'fa-shopping-basket'],
            'root'    => 'retina.ecom.orders.',
            'route'   => [
                'name' => 'retina.ecom.orders.index'
            ],
        ];

        $groupNavigation['favourites'] = [
            'label' => __('Favourites'),
            'icon' => ['fal', 'fa-heart'],
            'root' => 'retina.ecom.favourites.',
            'route' => [
                'name' => 'retina.ecom.favourites.index'
            ],
            'topMenu' => [

            ]
        ];

        $groupNavigation['invoices'] = [
            'label'   => __('Invoices'),
            'icon'    => ['fal', 'fa-file-invoice-dollar'],
            'root'    => 'retina.ecom.invoices.',
            'route'   => [
                'name' => 'retina.ecom.invoices.index'
            ],
        ];



        $groupNavigation['sysadmin'] = [
            'label'   => __('manage account'),
            'icon'    => ['fal', 'fa-users-cog'],
            'root'    => 'retina.sysadmin.',
            'route'   => [
                'name' => 'retina.sysadmin.dashboard'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'label' => __('users'),
                        'icon'  => ['fal', 'fa-user-circle'],
                        'root'  => 'retina.sysadmin.web-users.',
                        'route' => [
                            'name' => 'retina.sysadmin.web-users.index',

                        ]
                    ],

                    [
                        'label' => __('account settings'),
                        'icon'  => ['fal', 'fa-cog'],
                        'root'  => 'retina.sysadmin.settings.',
                        'route' => [
                            'name' => 'retina.sysadmin.settings.edit',

                        ]
                    ],
                ]
            ]
        ];

        return $groupNavigation;
    }
}
