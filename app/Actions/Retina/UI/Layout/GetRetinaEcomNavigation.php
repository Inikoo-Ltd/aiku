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
