<?php

/*
 * author Arya Permana - Kirin
 * created on 28-01-2025-08h-35m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Ordering\Order;

use App\Models\Catalogue\Shop;

trait WithOrdersSubNavigation
{
    protected function getOrdersNavigation(Shop $shop): array
    {
        return [
            [
                'number' => $shop->orderingStats->number_orders,
                'label' => __('Orders'),
                'route' => [
                    'name' => 'grp.org.shops.show.ordering.orders.index',
                    'parameters' => [
                        'organisation' => $shop->organisation->slug,
                        'shop' => $shop->slug,
                    ],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-shopping-cart'],
                    'tooltip' => __('Orders'),
                ],
            ],
            [
                'number' => $shop->orderingStats->number_purges,
                'label' => __('Purges'),
                'route' => [
                    'name' => 'grp.org.shops.show.ordering.purges.index',
                    'parameters' => [
                        'organisation' => $shop->organisation->slug,
                        'shop' => $shop->slug,
                    ],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-trash-alt'],
                    'tooltip' => __('Purges'),
                ],
            ],

        ];
    }
}
