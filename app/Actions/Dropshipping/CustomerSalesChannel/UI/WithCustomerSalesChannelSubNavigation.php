<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-54m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\UI;

use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

trait WithCustomerSalesChannelSubNavigation
{
    public function getCustomerSalesChannelSubNavigationHead(CustomerSalesChannel $customerSalesChannel, ActionRequest $request, $titleRight = '', $iconRight = null): array
    {
        return [
            'subNavigation' => $this->getCustomerSalesChannelSubNavigation($customerSalesChannel),
            ...$this->getCustomerSalesChannelSubNavigationHeadProperties($customerSalesChannel, $titleRight, $iconRight),
        ];
    }


    public function getCustomerSalesChannelSubNavigationHeadProperties(CustomerSalesChannel $customerSalesChannel, $titleRight = '', $iconRight = null): array
    {
        $title = $customerSalesChannel->name;
        if (!$title) {
            $title = $customerSalesChannel->reference;
        }


        return [
            'title'      => $title,
            'icon'       => [
                'icon'          => ['fal', 'fa-code-branch'],
                'icon_rotation' => 90,
                'title'         => __('channel')
            ],
            'iconRight'  => $iconRight,
            'titleRight' => $titleRight,
            'platform'   => $customerSalesChannel->platform,
            'afterTitle' => [
                'label' => ' @'.$customerSalesChannel->customer->name.' ('.$customerSalesChannel->platform->name.')',
            ],
        ];
    }


    public function getCustomerSalesChannelSubNavigation(CustomerSalesChannel $customerSalesChannel): array
    {
        $subNavigation = [];

        $subNavigation[] = [
            'isAnchor' => true,
            'route'    => [
                'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show',
                'parameters' => [
                    'organisation' => $customerSalesChannel->shop->organisation->slug,
                    'shop'         => $customerSalesChannel->shop->slug,
                    'customer'     => $customerSalesChannel->customer->slug,
                    'customerSalesChannel' => $customerSalesChannel->slug,
                ]
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
                'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.portfolios.index',
                'parameters' => [
                    'organisation' => $customerSalesChannel->shop->organisation->slug,
                    'shop'         => $customerSalesChannel->shop->slug,
                    'customer'     => $customerSalesChannel->customer->slug,
                    'customerSalesChannel' => $customerSalesChannel->slug,
                ]

            ],
            'leftIcon' => [
                'icon'    => 'fal fa-bookmark',
                'tooltip' => __('portfolio'),
            ],
        ];

        $subNavigation[] = [
            "number"   => $customerSalesChannel->number_customer_clients,
            'label'    => __('Clients'),
            'route'    => [
                'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.index',
                'parameters' => [
                    'organisation' => $customerSalesChannel->shop->organisation->slug,
                    'shop'         => $customerSalesChannel->shop->slug,
                    'customer'     => $customerSalesChannel->customer->slug,
                    'customerSalesChannel' => $customerSalesChannel->slug,
                ]

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
                'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.index',
                'parameters' => [
                    'organisation' => $customerSalesChannel->shop->organisation->slug,
                    'shop'         => $customerSalesChannel->shop->slug,
                    'customer'     => $customerSalesChannel->customer->slug,
                    'customerSalesChannel' => $customerSalesChannel->slug,
                ]

            ],
            'leftIcon' => [
                'icon'    => 'fal fa-shopping-cart',
                'tooltip' => __('order'),
            ],
        ];

        return $subNavigation;
    }
}
