<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 17 May 2025 17:42:49 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRetinaFulfilmentCustomerSalesChannelNavigation
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel): array
    {
        $platformNavigation = [];


        $platformNavigation['baskets'] = [
            'label'       => __('Baskets'),
            'icon'        => ['fal', 'fa-shopping-basket'],
            'root'        => 'retina.fulfilment.dropshipping.customer_sales_channels.basket.',
            'right_label' => [
                'label' => __('29'),
                'class' => 'bg-yellow-500 text-green-500'
            ],
            'route'       => [
                'name'       => 'retina.fulfilment.dropshipping.customer_sales_channels.basket.index',
                'parameters' => [$customerSalesChannel->slug]
            ],
        ];




        $platformNavigation['portfolios'] = [
            'label'       => __('Portfolio'),
            'icon'        => ['fal', 'fa-cube'],
            'root'        => 'retina.fulfilment.dropshipping.customer_sales_channels.portfolios.',
            'route'       => [
                'name'       => 'retina.fulfilment.dropshipping.customer_sales_channels.portfolios.index',
                'parameters' => [$customerSalesChannel->slug]
            ],
            'right_label' => [
                'label'        => __('14'),
                'class'        => 'bg-red-500 text-white',
                'is_important' => true
            ],

        ];

        $platformNavigation['client'] = [
            'label' => __('Clients'),
            'icon'  => ['fal', 'fa-user-friends'],
            'root'  => 'retina.fulfilment.dropshipping.customer_sales_channels.client.',
            'route' => [
                'name'       => 'retina.fulfilment.dropshipping.customer_sales_channels.client.index',
                'parameters' => [$customerSalesChannel->slug]
            ],
        ];

        $platformNavigation['orders'] = [
            'label' => __('Orders'),
            'icon'  => ['fal', 'fa-shopping-cart'],
            'root'  => 'retina.fulfilment.dropshipping.customer_sales_channels.orders.',
            'route' => [
                'name'       => 'retina.fulfilment.dropshipping.customer_sales_channels.orders.index',
                'parameters' => [$customerSalesChannel->slug]
            ],
        ];

        // if($customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL)
        // {
        //     $platformNavigation['api_token'] = [
        //         'label' => __('Api'),
        //         'icon'  => ['fal', 'fa-key'],
        //         'root'  => 'retina.fulfilment.dropshipping.customer_sales_channels.api.',
        //         'route' => [
        //             'name'       => 'retina.fulfilment.dropshipping.customer_sales_channels.api.dashboard',
        //             'parameters' => [$customerSalesChannel->slug]
        //         ],
        //     ];
        // }


        return $platformNavigation;
    }
}
