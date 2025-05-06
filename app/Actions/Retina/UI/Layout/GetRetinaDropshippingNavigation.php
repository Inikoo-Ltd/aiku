<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Feb 2024 22:34:23 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\CRM\WebUser;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRetinaDropshippingNavigation
{
    use AsAction;

    public function handle(WebUser $webUser): array
    {
        $customer = $webUser->customer;
        $groupNavigation = [];

        if ($customer?->shop?->type === ShopTypeEnum::DROPSHIPPING) {
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

            // $groupNavigation['portfolios'] = [
            //     'label' => __('Portfolio'),
            //     'icon' => ['fal', 'fa-pallet'],
            //     'root' => 'retina.dropshipping.portfolios.',
            //     'route' => [
            //         'name' => 'retina.dropshipping.portfolios.index'
            //     ],
            //     'topMenu' => [
            //         'subSections' => [
            //             [
            //                 'label' => __('My Portfolio'),
            //                 'icon' => ['fal', 'fa-cube'],
            //                 'root' => 'retina.dropshipping.portfolios.index',
            //                 'route' => [
            //                     'name' => 'retina.dropshipping.portfolios.index'
            //                 ]
            //             ],
            //             [
            //                 'label' => __('All Products'),
            //                 'icon' => ['fal', 'fa-cube'],
            //                 'root' => 'retina.dropshipping.portfolios.products.index',
            //                 'route' => [
            //                     'name' => 'retina.dropshipping.portfolios.products.index'
            //                 ]
            //             ]
            //         ]
            //     ]
            // ];

            // $groupNavigation['clients'] = [
            //     'label' => __('Clients'),
            //     'icon' => ['fal', 'fa-user'],
            //     'root' => 'retina.dropshipping.client.',
            //     'route' => [
            //         'name' => 'retina.dropshipping.client.index'
            //     ],
            //     'topMenu' => [

            //     ]
            // ];

            $groupNavigation['orders'] = [
                'label' => __('Orders'),
                'icon' => ['fal', 'fa-box'],
                'root' => 'retina.dropshipping.orders.',
                'route' => [
                    'name' => 'retina.dropshipping.orders.index'
                ],
                'topMenu' => [

                ]
            ];
        }

        $groupNavigation['platform'] = [
            'label' => __('Channels'),
            'icon' => ['fal', 'fa-parachute-box'],
            'root' => 'retina.dropshipping.platform.',
            'route' => [
                'name' => 'retina.dropshipping.platform.dashboard'
            ]
        ];

        $platforms_navigation = [];

        foreach (
            $customer->platforms()->get() as $platform
        ) {
            $platforms_navigation[] = [
                'type'          => $platform->type,
                'slug'          => $platform->slug,
                'root'          => 'retina.dropshipping.platforms.',
                'subNavigation' => GetRetinaDropshippingPlatformNavigation::run($webUser, $platform)
            ];
        }

        if (!blank($platforms_navigation)) {
            $groupNavigation['platforms_navigation'] = [
                'platforms_navigation'       => [
                    'label'      => __('platforms'),
                    'icon'       => "fal fa-store-alt",
                    'navigation' => array_reverse($platforms_navigation)
                ],
            ];
        }

        $groupNavigation['topup'] = [
            'label' => __('Top Up'),
            'icon' => ['fal', 'fa-money-bill-wave'],
            'root' => 'retina.topup.',
            'route' => [
                'name' => 'retina.topup.dashboard'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'label' => __('Topups'),
                        'icon'  => ['fal', 'fa-money-bill-wave'],
                        'root'  => 'retina.topup.',
                        'route' => [
                            'name' => 'retina.topup.index',

                        ]
                    ],
                ]
            ]
        ];

        if ($webUser->is_root) {
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
        }

        return $groupNavigation;
    }
}
