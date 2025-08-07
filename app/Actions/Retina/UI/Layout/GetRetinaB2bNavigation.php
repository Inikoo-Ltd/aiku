<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Feb 2024 22:34:23 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use App\Models\CRM\WebUser;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRetinaB2bNavigation
{
    use AsAction;

    public function handle(WebUser $webUser): array
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
            'icon' => ['fal', 'fa-shopping-basket'],
            'root' => 'retina.ecom.basket.',
            'route' => [
                'name' => 'retina.ecom.basket.show'
            ],
            'topMenu' => [

            ]
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

        $groupNavigation['invoice'] = [
            'label'   => __('Invoice'),
            'icon'    => ['fal', 'fa-file-invoice-dollar'],
            'root'    => 'retina.dropshipping.invoices.',
            'route'   => [
                'name' => 'retina.dropshipping.invoices.index'
            ],
            // 'topMenu' => [
            //     'subSections' => [
            //         [
            //             'label' => __('View Top ups'),
            //             'icon'  => ['far', 'fa-eye'],
            //             'root'  => 'retina.top_up.',
            //             'route' => [
            //                 'name' => 'retina.top_up.index',

            //             ]
            //         ],
            //     ]
            // ]
        ];

        $groupNavigation['top_up'] = [
            'label'   => __('Top Up'),
            'icon'    => ['fal', 'fa-money-bill-wave'],
            'root'    => 'retina.top_up.',
            'route'   => [
                'name' => 'retina.top_up.dashboard'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'label' => __('View Top ups'),
                        'icon'  => ['far', 'fa-eye'],
                        'root'  => 'retina.top_up.',
                        'route' => [
                            'name' => 'retina.top_up.index',

                        ]
                    ],
                ]
            ]
        ];

        $groupNavigation['sysadmin'] = [
            'label'   => __('manage account'),
            'icon'    => ['fal', 'fa-users-cog'],
            'root'    => 'retina.sysadmin.',
            'route'   => [
                'name' => 'retina.sysadmin.dropshipping.dashboard'
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
