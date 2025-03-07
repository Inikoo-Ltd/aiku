<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 15-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

namespace App\Actions\Pupil\Dashboard;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowPupilDashboard
{
    use AsAction;


    public function asController(ActionRequest $request): Response
    {
        $routes = [];
        /** @var \App\Models\Dropshipping\ShopifyUser $shopifyUser */
        $shopifyUser = $request->user('pupil');

        if ($shopifyUser) {
            $routes = [
                'routes'                => [
                    'products' => [
                        'name'       => 'pupil.products',
                        'parameters' => [
                            'shopifyUser' => $shopifyUser->id
                        ]
                    ],
                    'store_product' => [
                        'name'       => 'pupil.shopify_user.product.store',
                        'parameters' => [
                            'shopifyUser' => $shopifyUser->id
                        ]
                    ],
                    'get_started' => [
                        'name'       => 'pupil.shopify_user.get_started.store',
                        'parameters' => [
                            'shopifyUser' => $shopifyUser->id
                        ],
                        'method'    => 'post'
                    ]
                ]
            ];
        }

        return Inertia::render('Dashboard/PupilWelcome', [
             'shop'                  => $shopifyUser?->customer?->shop?->name,
             'shopUrl'                  => $this->getShopUrl($shopifyUser?->customer?->shop),
            'user'                  => $shopifyUser,
            'showIntro'             => !Arr::get($shopifyUser?->settings, 'webhooks'),
            'shops' => Shop::where('type', ShopTypeEnum::FULFILMENT->value)->get()->map(function ($shop) {
                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'domain' => $this->getShopUrl($shop),
                ];
            }),
            ...$routes
        ]);
    }

    public function getShopUrl(?Shop $shop): string|null
    {
        if (!$shop) {
            return null;
        }

        return match (app()->environment()) {
            'production' => 'https://'.$shop?->website?->domain . '/app',
            'staging' => 'https://canary.'.$shop?->website?->domain . '/app',
            default => 'https://fulfilment.test/app'
        };
    }

    // public function getBreadcrumbs($label = null): array
    // {
    //     return [
    //         [

    //             'type'   => 'simple',
    //             'simple' => [
    //                 'icon'  => 'fal fa-home',
    //                 'label' => $label,
    //                 'route' => [
    //                     'name' => 'retina.dashboard.show'
    //                 ]
    //             ]

    //         ],

    //     ];
    // }
}
