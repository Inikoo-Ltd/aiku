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
        $customer        = $webUser->customer;
        $groupNavigation = [];

        if ($customer?->shop?->type === ShopTypeEnum::DROPSHIPPING) {
            $groupNavigation['dashboard'] = [
                'label'   => __('Dashboard'),
                'icon'    => ['fal', 'fa-tachometer-alt'],
                'root'    => 'retina.dashboard.',
                'route'   => [
                    'name' => 'retina.dashboard.show'
                ],
                'topMenu' => [

                ]
            ];
        }

        // $groupNavigation['platform'] = [
        //     'label'         => __('Channels'),
        //     'icon'          => 'fal fa-code-branch',
        //     'icon_rotation'   => 90,
        //     'root'  => 'retina.dropshipping.platform.',
        //     'route' => [
        //         'name' => 'retina.dropshipping.platform.dashboard'
        //     ]
        // ];

        $platforms_navigation = [];


        foreach (
            $customer->customerSalesChannels as $salesChannel
        ) {
            $platforms_navigation[] = [
                'type'          => $salesChannel->platform->type,
                'slug'          => $salesChannel->platform->slug,
                'root'          => 'retina.dropshipping.platforms.',
                'subNavigation' => GetRetinaDropshippingPlatformNavigation::run($webUser, $salesChannel->platform)
            ];
        }

        // if (!blank($platforms_navigation)) {
            $groupNavigation['platforms_navigation'] = [
                'type'  => 'horizontal',
                'before_horizontal' => [
                    'subNavigation' => [
                        [
                            'label'         => __('Channels'),
                            'icon'          => 'fal fa-code-branch',
                            'icon_rotation'   => 90,
                            'root'  => 'retina.dropshipping.platform.',
                            'route' => [
                                'name' => 'retina.dropshipping.platform.dashboard'
                            ]
                        ]
                    ]
                ],
                'horizontal_navigations'    => [  // TODO: below is dummy data, change to correct one
                    [
                        'label'         => __('Channels 11111'),
                        'icon'        => 'fal fa-tachometer-alt',
                        'key'           => 'asdzxc_platform',
                        'root'          => 'retina.dropshipping.platforms.',
                        'route'         => [
                            'name' => 'retina.dropshipping.platforms.dashboard',
                            'parameters' => [
                                'platform' => 'manual'
                            ]
                        ],
                        'subNavigation' => GetRetinaDropshippingPlatformNavigation::run($webUser, $salesChannel->platform)
                    ], [
                        'label'         => __('Channels 22222'),
                        'icon'          => 'fal fa-shopping-basket',
                        'key'           => 'rtyfgh_platform',
                        'root'          => 'retina.dropshipping.platforms.',
                        'route'         => [
                            'name' => 'retina.dropshipping.platforms.dashboard',
                            'parameters' => [
                                'platform' => 'manual'
                            ]
                        ],
                        'subNavigation' => GetRetinaDropshippingPlatformNavigation::run($webUser, $salesChannel->platform)
                    ]
                ],
            ];
        // }



        if ($webUser->customer->is_dropshipping) {
            $groupNavigation['top_up'] = [
                'label'         => __('Top Up'),
                'icon'          => ['fal', 'fa-money-bill-wave'],
                'root'          => 'retina.top_up.',
                'route'         => [
                    'name'      => 'retina.top_up.dashboard'
                ],
                'topMenu'   => [
                    'subSections' => [
                        [
                            'label' => __('Top ups'),
                            'icon'  => ['fal', 'fa-money-bill-wave'],
                            'root'  => 'retina.top_up.',
                            'route' => [
                                'name' => 'retina.top_up.index',

                            ]
                        ],
                    ]
                ]
            ];

            $groupNavigation['saved_credit_cards'] = [
                'label' => __('Saved Cards'),
                'icon'  => ['fal', 'fa-credit-card'],
                'root'  => 'retina.dropshipping.mit_saved_cards.',
                'route' => [
                    'name' => 'retina.dropshipping.mit_saved_cards.dashboard'
                ],
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

        }

        return $groupNavigation;
    }
}
