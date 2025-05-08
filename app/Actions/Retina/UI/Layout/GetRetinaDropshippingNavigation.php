<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Feb 2024 22:34:23 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\Platform;
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

            // $groupNavigation['orders'] = [
            //     'label' => __('Orders'),
            //     'icon' => ['fal', 'fa-box'],
            //     'root' => 'retina.dropshipping.orders.',
            //     'route' => [
            //         'name' => 'retina.dropshipping.orders.index'
            //     ],
            //     'topMenu' => [

            //     ]
            // ];
            $groupNavigation['top_up'] = [
                'label' => __('Top Up'),
                'icon' => ['fal', 'fa-money-bill-wave'],
                'root' => 'retina.top_up.',
                'route' => [
                    'name' => 'retina.top_up.dashboard'
                ],
                'topMenu' => [
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

        /** @var Platform $platform */
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

        $groupNavigation['saved_credit_card'] = [
            'label' => __('Saved Credit Card'),
            'icon' => ['fal', 'fa-money-bill-wave'],
            'root' => 'retina.top_up.',
            'route' => [
                'name' => 'retina.dropshipping.saved-credit-card.show'
            ],
        ];

        return $groupNavigation;
    }
}
