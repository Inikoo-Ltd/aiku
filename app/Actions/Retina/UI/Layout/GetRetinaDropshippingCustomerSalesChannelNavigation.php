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

        $isManual = $customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL;

        if ($isManual) {
            $platformNavigation['baskets'] = [
                'label'       => __('Baskets'),
                'icon'        => ['fal', 'fa-shopping-basket'],
                'root'        => 'retina.dropshipping.customer_sales_channels.basket.',
                'right_label' => [
                    'number' => $customerSalesChannel->number_orders_state_creating,
                    'class'  => 'bg-yellow-500 text-green-500'
                ],
                'route'       => [
                    'name'       => 'retina.dropshipping.customer_sales_channels.basket.index',
                    'parameters' => [$customerSalesChannel->slug]
                ],
            ];
        }



        $platformNavigation['portfolios'] = [
            'label'       => __('My Products'),
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

        if ($isManual) {
            $platformNavigation['api_token'] = [
                'label' => __('Api'),
                'icon'  => ['fal', 'fa-key'],
                'root'  => 'retina.dropshipping.customer_sales_channels.api.',
                'route' => [
                    'name'       => 'retina.dropshipping.customer_sales_channels.api.dashboard',
                    'parameters' => [$customerSalesChannel->slug]
                ],
            ];
        }



        return $platformNavigation;
    }
}
