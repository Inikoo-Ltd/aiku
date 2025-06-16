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
    use GetPlatformLogo;

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


        $platforms_navigation = [];


        foreach (
            $customer->customerSalesChannels as $customerSalesChannels
        ) {


            $reference = $customerSalesChannels->reference ?? 'n/a';

            $platforms_navigation[] = [
                'id'            => $customerSalesChannels->id,
                'type'          => $customerSalesChannels->platform->type,
                'slug'          => $customerSalesChannels->slug,
                'key'           => $customerSalesChannels->slug,
                'label'         => $customerSalesChannels->platform->name.' ('.$reference.')',
                'img'           => $this->getPlatformLogo($customerSalesChannels),
                'route'         => [
                    'name'       => 'retina.dropshipping.customer_sales_channels.show',
                    'parameters' => [
                        'customerSalesChannel' => $customerSalesChannels->slug
                    ]
                ],
                'root'          => 'retina.dropshipping.customer_sales_channels.',
                'subNavigation' => GetRetinaDropshippingCustomerSalesChannelNavigation::run($customerSalesChannels)
            ];
        }

        $groupNavigation['platforms_navigation'] = [
            'type'                   => 'horizontal',
            'before_horizontal'      => [
                'subNavigation' => [
                    [
                        'label'         => __('Channels'),
                        'icon'          => 'fal fa-code-branch',
                        'icon_rotation' => 90,
                        'root'          => 'retina.dropshipping.customer_sales_channels.',
                        'route'         => [
                            'name' => 'retina.dropshipping.customer_sales_channels.index'
                        ]
                    ]
                ]
            ],
            'horizontal_navigations' => $platforms_navigation
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


        return $groupNavigation;
    }
}
