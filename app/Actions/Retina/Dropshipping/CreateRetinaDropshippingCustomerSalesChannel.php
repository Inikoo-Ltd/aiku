<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 19-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

namespace App\Actions\Retina\Dropshipping;

use App\Actions\Dropshipping\Tiktok\User\AuthenticateTiktokAccount;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateRetinaDropshippingCustomerSalesChannel extends RetinaAction
{
    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisation($request);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $customer = $this->customer;

        $title = __('Create Channels');

        return Inertia::render(
            'Dropshipping/DropshippingCreateChannel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'          => 'fal fa-code-branch',
                    'icon_rotation' => 90,
                ],
                'shopify_url' => '.' . config('shopify-app.my_shopify_domain'),
                'unlinkRoute' => [
                    'name'       => 'retina.dropshipping.platform.shopify_user.delete',
                    'parameters' => [],
                    'method'     => 'delete'
                ],
                'fetchCustomerRoute' => [
                    'name'       => 'retina.dropshipping.client.fetch',
                    'parameters' => []
                ],
/*                'connectRoute' => $customer->shopifyUser ? [
                    'url'       => route('pupil.authenticate', [
                        'shop' => $customer->shopifyUser?->name
                    ])
                ] : null,*/
                'total_channels' => [
                    'manual' => DB::table('customer_sales_channels')->where('customer_id', $customer->id)->leftJoin('platforms', 'platforms.id', 'customer_sales_channels.platform_id')->where('platforms.type', PlatformTypeEnum::MANUAL->value)->count(),
                    'shopify'   => DB::table('customer_sales_channels')->where('customer_id', $customer->id)->leftJoin('platforms', 'platforms.id', 'customer_sales_channels.platform_id')->where('platforms.type', PlatformTypeEnum::SHOPIFY->value)->count(),
                    'tiktok'    => DB::table('customer_sales_channels')->where('customer_id', $customer->id)->leftJoin('platforms', 'platforms.id', 'customer_sales_channels.platform_id')->where('platforms.type', PlatformTypeEnum::TIKTOK->value)->count(),
                    'woocommerce' => DB::table('customer_sales_channels')->where('customer_id', $customer->id)->leftJoin('platforms', 'platforms.id', 'customer_sales_channels.platform_id')->where('platforms.type', PlatformTypeEnum::WOOCOMMERCE->value)->count(),
                ],
                'tiktokAuth' => [
                    'url' => AuthenticateTiktokAccount::make()->redirectToTikTok($customer),
                    'isAuthenticated' => AuthenticateTiktokAccount::make()->checkIsAuthenticated($customer),
                    'isAuthenticatedExpired' => AuthenticateTiktokAccount::make()->checkIsAuthenticatedExpired($customer),
                    'tiktokName' => $customer->tiktokUser?->name,
                    'deleteAccountRoute' => [
                        'method' => 'delete',
                        'name' => 'retina.models.dropshipping.tiktok.delete',
                        'parameters' => [
                            'tiktokUser' => $customer->tiktokUser?->id
                        ]
                    ]
                ],
                'type_shopify'  => [
                    'connectRoute' => $customer->shopifyUser ? [
                        'url'       => route('pupil.authenticate', [
                            'shop' => $customer->shopifyUser?->name
                        ])
                    ] : null,
                    'createRoute' => [
                        'name'       => 'retina.dropshipping.platform.shopify_user.store',
                        'parameters' => [],
                        'method'     => 'post'
                    ],
                ],
                'type_manual'   => [
                    'isAuthenticated' => $customer->customerSalesChannelsXXX()->where('type', PlatformTypeEnum::MANUAL->value)->exists(),
                    'url'       => route('retina.models.customer_sales_channel.manual.store')
                ],
                'type_tiktok'   => [

                ],
                'type_woocommerce' => [
                    'connectRoute' => [
                        'name' => 'retina.dropshipping.platform.wc.authorize',
                        'parameters' => [],
                        'method' => 'post'
                    ],
                    'isConnected' => $customer->customerSalesChannelsXXX()->where('type', PlatformTypeEnum::WOOCOMMERCE->value)->exists()
                ],
            ]
        );
    }

    public function getBreadcrumbs($routeParameters): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'retina.dropshipping.platform.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Channels'),
                        ]
                    ]
                ]
            );
    }
}
