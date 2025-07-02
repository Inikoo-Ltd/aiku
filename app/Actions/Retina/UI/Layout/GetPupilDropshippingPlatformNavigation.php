<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Feb 2024 22:34:23 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\Concerns\AsAction;

class GetPupilDropshippingPlatformNavigation
{
    use AsAction;

    public function handle(?ShopifyUser $shopifyUser, Platform $platform): array
    {
        $platformNavigation = [];

        if ($shopifyUser->customer->shopifyUser) {
            $tabs = [];

            if (!$shopifyUser->customer->fulfilmentCustomer or $platform->type !== PlatformTypeEnum::SHOPIFY) {
                $tabs = [
                    [
                        'label' => __('All Products'),
                        'icon' => ['fal', 'fa-cube'],
                        'root' => 'pupil.dropshipping.platforms.portfolios.products.index',
                        'route' => [
                            'name' => 'pupil.dropshipping.platforms.portfolios.products.index',
                            'parameters' => [$platform->slug]
                        ],
                    ]
                ];
            }

            $platformNavigation['portfolios'] = [
                'label' => __('Portfolios'),
                'icon' => ['fal', 'fa-cube'],
                'root' => 'pupil.dropshipping.platforms.portfolios.',
                'route' => [
                    'name' => 'pupil.dropshipping.platforms.portfolios.index',
                    'parameters' => [$platform->slug]
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'label' => __('My Products'),
                            'icon' => ['fal', 'fa-cube'],
                            'root' => 'pupil.dropshipping.platforms.portfolios.index',
                            'route' => [
                                'name' => 'pupil.dropshipping.platforms.portfolios.index',
                                'parameters' => [$platform->slug]
                            ],
                        ],
                        ...$tabs
                    ]
                ]
            ];
        }

        $platformNavigation['client'] = [
            'label' => __('Client'),
            'icon' => ['fal', 'fa-user-friends'],
            'root' => 'pupil.dropshipping.platforms.client.',
            'route' => [
                'name' => 'pupil.dropshipping.platforms.client.index',
                'parameters' => [$platform->slug]
            ],
        ];

        $platformNavigation['orders'] = [
            'label' => __('Orders'),
            'icon' => ['fal', 'fa-money-bill-wave'],
            'root' => 'pupil.dropshipping.platforms.orders.',
            'route' => [
                'name' => 'pupil.dropshipping.platforms.orders.index',
                'parameters' => [$platform->slug]
            ],
        ];

        return $platformNavigation;
    }
}
