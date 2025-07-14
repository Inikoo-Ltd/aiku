<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 15-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

namespace App\Actions\Pupil\Dashboard;

use App\Actions\Retina\UI\Dashboard\GetRetinaDropshippingHomeData;
use App\Actions\Retina\UI\Dashboard\GetRetinaFulfilmentHomeData;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowPupilDashboard
{
    use AsAction;


    public function asController(ActionRequest $request): Response
    {
        $additionalProps = [];
        $routes          = [];
        /** @var \App\Models\Dropshipping\ShopifyUser $shopifyUser */
        $shopifyUser = $request->user('pupil');

        if ($shopifyUser) {
            $routes = [
                'routes' => [
                    'products'      => [
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
                    'get_started'   => [
                        'name'       => 'pupil.shopify_user.get_started.store',
                        'parameters' => [
                            'shopifyUser' => $shopifyUser->id
                        ],
                        'method'     => 'post'
                    ]
                ]
            ];
        }

        $query = Shop::where('type', ShopTypeEnum::FULFILMENT->value)->get();

        if ($shopifyUser->customer) {
            $query = Shop::where('id', $shopifyUser->customer->shop_id)->get();

            $additionalProps = [
                'data' => match ($shopifyUser?->customer->shop->type) {
                    ShopTypeEnum::FULFILMENT => GetRetinaFulfilmentHomeData::run($shopifyUser?->customer?->fulfilmentCustomer, $request),
                    ShopTypeEnum::DROPSHIPPING => GetRetinaDropshippingHomeData::run($shopifyUser?->customer, $request),
                    default => []
                },
            ];
        }


        if (!$shopifyUser?->customer) {
            $render_page = 'Intro';
        } elseif ($shopifyUser?->customer?->shop?->name) {
            $render_page = 'WelcomeShop';
        } else {
            $render_page = 'Dashboard/PupilWelcome';
        }

        return Inertia::render($render_page, [
            'shop'    => $shopifyUser?->customer?->shop?->name,
            'shopUrl' => $this->getShopUrl($shopifyUser?->customer?->shop, $shopifyUser),
            'user'    => $shopifyUser,
            // 'showIntro'             => !Arr::get($shopifyUser?->settings, 'webhooks'),
            'shops'   => $query->map(function ($shop) {
                return [
                    'id'   => $shop->id,
                    'name' => $shop->name
                ];
            }),
            ...$routes,
            ...$additionalProps
        ]);
    }

    public function getShopUrl(?Shop $shop, ShopifyUser $shopifyUser): string|null
    {
        if (!$shop) {
            return null;
        }

        $subdomain = 'www';
        if ($shop->website->is_migrating) {
            $subdomain = 'v2';
        }

        return match (app()->environment()) {
            'production' => 'https://'.$subdomain.'.'.$shop->website?->domain.'/app/auth-shopify?shopify='.base64_encode($shopifyUser->password),
            'staging' => 'https://canary.'.$shop->website?->domain.'/app/auth-shopify?shopify='.base64_encode($shopifyUser->password),
            default => 'https://fulfilment.test/app/auth-shopify?shopify='.base64_encode($shopifyUser->password)
        };
    }


}
