<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-54m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\CRM\Customer\UI;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\ModelHasPlatform;
use Lorisleiva\Actions\ActionRequest;

trait WithCustomerPlatformSubNavigation
{
    public function getCustomerPlatformSubNavigation(ModelHasPlatform $modelHasPlatform, Customer $customer, ActionRequest $request): array
    {
        $subNavigation = [];

        $subNavigation[] = [
            'isAnchor' => true,
            'route' => [
                'name'      => 'grp.org.shops.show.crm.customers.show.platforms.show',
                'parameters' => $request->route()->originalParameters()

            ],

            'label'     => __('Channel'),
            'leftIcon'  => [
                'icon'    => 'fal fa-parachute-box',
                'tooltip' => __('channel'),
            ],
        ];

        $subNavigation[] = [
            'label'     => __('Portfolios'),
            'route' => [
                'name'      => 'grp.org.shops.show.crm.customers.show.platforms.show.portfolios.index',
                'parameters' => $request->route()->originalParameters()

            ],
            'leftIcon'  => [
                'icon'    => 'fal fa-box',
                'tooltip' => __('portfolio'),
            ],
        ];

        if ($modelHasPlatform->platform->type == PlatformTypeEnum::AIKU) {
            $subNavigation[] = [
                'label'     => __('Clients'),
                'route' => [
                    'name'      => 'grp.org.shops.show.crm.customers.show.platforms.show.customer-clients.aiku.index',
                    'parameters' => $request->route()->originalParameters()

                ],
                'leftIcon'  => [
                    'icon'    => 'fal fa-users',
                    'tooltip' => __('clients'),
                ],
            ];
        } else {
            $subNavigation[] = [
                'label'     => __('Clients'),
                'route' => [
                    'name'      => 'grp.org.shops.show.crm.customers.show.platforms.show.customer-clients.other-platform.index',
                    'parameters' => $request->route()->originalParameters()

                ],
                'leftIcon'  => [
                    'icon'    => 'fal fa-users',
                    'tooltip' => __('clients'),
                ],
            ];
        }
        $subNavigation[] = [
            'label'     => __('Orders'),
            // 'route' => [
            //     'name'      => 'grp.org.fulfilments.show.crm.customers.show.platforms.show.orders.index',
            //     'parameters' => $request->route()->originalParameters()

            // ],
            'leftIcon'  => [
                'icon'    => 'fal fa-shopping-cart',
                'tooltip' => __('order'),
            ],
        ];

        return $subNavigation;
    }
}
