<?php

/*
 * author Arya Permana - Kirin
 * created on 02-04-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment;

use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

trait WithFulfilmentCustomerPlatformSubNavigation
{
    public function getFulfilmentCustomerPlatformSubNavigation(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): array
    {
        $subNavigation = [];

        $subNavigation[] = [
            'isAnchor' => true,
            'route'    => [
                'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show',
                'parameters' => $request->route()->originalParameters()

            ],

            'label'    => __('Channel').': '.$customerSalesChannel->reference,
            'leftIcon' => [
                'icon'          => 'fal fa-code-branch',
                'icon_rotation' => 90,
                'tooltip'       => __('channel'),
            ],
        ];

        $subNavigation[] = [
            "number"   => $customerSalesChannel->number_portfolios,
            'label'    => __('Portfolios'),
            'route'    => [
                'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.portfolios.index',
                'parameters' => $request->route()->originalParameters()

            ],
            'leftIcon' => [
                'icon'    => 'fal fa-box',
                'tooltip' => __('portfolio'),
            ],
        ];

        $subNavigation[] = [
            "number"   => $customerSalesChannel->number_customer_clients,
            'label'    => __('Clients'),
            'route'    => [
                'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.index',
                'parameters' => $request->route()->originalParameters()

            ],
            'leftIcon' => [
                'icon'    => 'fal fa-users',
                'tooltip' => __('clients'),
            ],
        ];

        $subNavigation[] = [
            "number"   => $customerSalesChannel->number_orders,
            'label'    => __('Orders'),
            'route'    => [
                'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.orders.index',
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
