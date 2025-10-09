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
use App\Enums\Catalogue\Shop\ShopStateEnum;
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
        /** @var ShopifyUser $shopifyUser */
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

        $query = Shop::where('slug', 'awd')
            ->where('state', ShopStateEnum::OPEN)
            ->get();

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


        $render_page = 'Intro';

        if ($shopifyUser->customer) {
            $render_page = 'Dashboard/PupilWelcome';
        }



        return Inertia::render($render_page, [
            'shop'    => $shopifyUser?->customer?->shop?->name,
            'shopUrl' => 'https://' . $shopifyUser?->customer?->shop?->website?->domain . '/app/login?ref=/app/dropshipping/channels/' . $shopifyUser?->customerSalesChannel?->slug,
            'user'    => $shopifyUser,
            'shops'   => $query->map(function (Shop $shop) {
                return [
                    'id'   => $shop->id,
                    'name' => $shop->name,
                    'domain' => 'https://' . $shop->website?->domain . '/app/login?ref=/app/dropshipping/sale-channels/create&modal=shopify'
                ];
            }),
            ...$routes,
            ...$additionalProps
        ]);
    }




}
