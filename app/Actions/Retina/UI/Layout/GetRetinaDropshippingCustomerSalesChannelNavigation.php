<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Feb 2024 22:34:23 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRetinaDropshippingCustomerSalesChannelNavigation
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel): array
    {
        $platformNavigation = [];

        $tabs = [];

        $platformNavigation['baskets'] = [
            'label'       => __('Baskets'),
            'icon'        => ['fal', 'fa-shopping-basket'],
            'root'        => 'retina.dropshipping.customer_sales_channels.basket.',
            'right_label' => [
                'number' => $customerSalesChannel->number_orders_state_creating,
                'class' => 'bg-yellow-500 text-green-500'
            ],
            'route'       => [
                'name'       => 'retina.dropshipping.customer_sales_channels.basket.index',
                'parameters' => [$customerSalesChannel->slug]
            ],
        ];


        // if ($customerSalesChannel->platform->type !== PlatformTypeEnum::SHOPIFY) {
        //     $tabs = [
        //         [
        //             'label' => __('All Products'),
        //             'icon'  => ['fal', 'fa-cube'],
        //             'root'  => 'retina.dropshipping.customer_sales_channels.portfolios.products.index',
        //             'route' => [
        //                 'name'       => 'retina.dropshipping.customer_sales_channels.portfolios.products.index',
        //                 'parameters' => [$customerSalesChannel->slug]
        //             ],
        //         ]
        //     ];
        // }

        $platformNavigation['portfolios'] = [
            'label'       => __('Portfolio'),
            'icon'        => ['fal', 'fa-cube'],
            'root'        => 'retina.dropshipping.customer_sales_channels.portfolios.',
            'route'       => [
                'name'       => 'retina.dropshipping.customer_sales_channels.portfolios.index',
                'parameters' => [$customerSalesChannel->slug]
            ],
            'right_label' => [
                'number'        => $customerSalesChannel->number_portfolios,
                'class'        => 'text-white',
            ],
            // 'topMenu'     => [
            //     'subSections' => [
            //         [
            //             'label' => __('My Portfolio'),
            //             'icon'  => ['fal', 'fa-cube'],
            //             'root'  => 'retina.dropshipping.customer_sales_channels.portfolios.index',
            //             'route' => [
            //                 'name'       => 'retina.dropshipping.customer_sales_channels.portfolios.index',
            //                 'parameters' => [$customerSalesChannel->slug]
            //             ],
            //         ],
            //         ...$tabs
            //     ]
            // ]
        ];

        $platformNavigation['client'] = [
            'label' => __('Clients'),
            'icon'  => ['fal', 'fa-user-friends'],
            'root'  => 'retina.dropshipping.customer_sales_channels.client.',
            'route' => [
                'name'       => 'retina.dropshipping.customer_sales_channels.client.index',
                'parameters' => [$customerSalesChannel->slug]
            ],
            'right_label' => [
                'number'        => $customerSalesChannel->number_customer_clients,
                'class'        => 'text-white',
            ],
        ];

        $platformNavigation['orders'] = [
            'label' => __('Orders'),
            'icon'  => ['fal', 'fa-shopping-cart'],
            'root'  => 'retina.dropshipping.customer_sales_channels.orders.',
            'route' => [
                'name'       => 'retina.dropshipping.customer_sales_channels.orders.index',
                'parameters' => [$customerSalesChannel->slug]
            ],
            'right_label' => [
                'number'        => $customerSalesChannel->number_orders - $customerSalesChannel->number_orders_state_creating - $customerSalesChannel->number_orders_state_cancelled,
                'class'        => 'text-white',
            ],
        ];

        $platformNavigation['polls'] = [
            'label' => __('Polls'),
            'icon'  => ['fal', 'fa-poll'],
            'root'  => 'retina.dropshipping.customer_sales_channels.polls.',
            'route' => [
                'name'       => 'retina.dropshipping.customer_sales_channels.polls.index',
                'parameters' => [$customerSalesChannel->slug]
            ],
            // 'right_label' => [
            //     'number'        => $customerSalesChannel->number_orders - $customerSalesChannel->number_orders_state_creating - $customerSalesChannel->number_orders_state_cancelled,
            //     'class'        => 'text-white',
            // ],
        ];


        $platformNavigation['api_token'] = [
            'label' => __('Api'),
            'icon'  => ['fal', 'fa-key'],
            'root'  => 'retina.dropshipping.customer_sales_channels.api.',
            'route' => [
                'name'       => 'retina.dropshipping.customer_sales_channels.api.dashboard',
                'parameters' => [$customerSalesChannel->slug]
            ],
        ];


        return $platformNavigation;
    }
}
