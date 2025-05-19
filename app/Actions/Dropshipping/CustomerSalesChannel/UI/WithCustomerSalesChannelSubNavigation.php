<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-54m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\UI;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

trait WithCustomerSalesChannelSubNavigation
{
    public function getCustomerPlatformSubNavigation(CustomerSalesChannel $customerHasPlatform, ActionRequest $request): array
    {
        $subNavigation = [];

        $subNavigation[] = [
            'isAnchor' => true,
            'route'    => [
                'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show',
                'parameters' => $request->route()->originalParameters()
            ],

            'label'    => __('Channel').': '.$customerHasPlatform->platform->name,
            'leftIcon' => [
                'icon'    => 'fal fa-store',
                'tooltip' => __('channel'),
            ],
        ];

        $subNavigation[] = [
            "number"   => $customerHasPlatform->number_portfolios,
            'label'    => __('Portfolios'),
            'route'    => [
                'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show.portfolios.index',
                'parameters' => $request->route()->originalParameters()

            ],
            'leftIcon' => [
                'icon'    => 'fal fa-bookmark',
                'tooltip' => __('portfolio'),
            ],
        ];

        if ($customerHasPlatform->platform->type == PlatformTypeEnum::MANUAL) {
            $subNavigation[] = [
                "number"   => $customerHasPlatform->number_customer_clients,
                'label'    => __('Clients'),
                'route'    => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show.customer_clients.manual.index',
                    'parameters' => $request->route()->originalParameters()

                ],
                'leftIcon' => [
                    'icon'    => 'fal fa-users',
                    'tooltip' => __('clients'),
                ],
            ];
        } else {
            $subNavigation[] = [
                "number"   => $customerHasPlatform->number_customer_clients,
                'label'    => __('Clients'),
                'route'    => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show.customer_clients.other_platform.index',
                    'parameters' => $request->route()->originalParameters()

                ],
                'leftIcon' => [
                    'icon'    => 'fal fa-users',
                    'tooltip' => __('clients'),
                ],
            ];
        }
        $subNavigation[] = [
            "number"   => $customerHasPlatform->number_orders,
            'label'    => __('Orders'),
            'route'    => [
                'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show.orders.index',
                'parameters' => $request->route()->originalParameters()

            ],
            'leftIcon' => [
                'icon'    => 'fal fa-shopping-cart',
                'tooltip' => __('order'),
            ],
        ];

        return $subNavigation;
    }
}
