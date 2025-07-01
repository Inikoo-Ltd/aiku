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
use App\Actions\Retina\Dropshipping\CustomerSalesChannel\UI\IndexDropshippingCustomerSalesChannels;
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
                    'name'       => 'retina.dropshipping.customer_clients.fetch',
                    'parameters' => []
                ],
                'connectRoute' => $customer->shopifyUser ? [
                    'url'       => route('pupil.authenticate', [
                        'shop' => $customer->shopifyUser?->name
                    ])
                ] : null,
                'total_channels' => [
                    'manual' => DB::table('customer_sales_channels')->where('customer_id', $customer->id)->leftJoin('platforms', 'platforms.id', 'customer_sales_channels.platform_id')->where('platforms.type', PlatformTypeEnum::MANUAL->value)->count(),
                    'shopify'   => DB::table('customer_sales_channels')->where('customer_id', $customer->id)->leftJoin('platforms', 'platforms.id', 'customer_sales_channels.platform_id')->where('platforms.type', PlatformTypeEnum::SHOPIFY->value)->count(),
                    'tiktok'    => DB::table('customer_sales_channels')->where('customer_id', $customer->id)->leftJoin('platforms', 'platforms.id', 'customer_sales_channels.platform_id')->where('platforms.type', PlatformTypeEnum::TIKTOK->value)->count(),
                    'woocommerce' => DB::table('customer_sales_channels')->where('customer_id', $customer->id)->leftJoin('platforms', 'platforms.id', 'customer_sales_channels.platform_id')->where('platforms.type', PlatformTypeEnum::WOOCOMMERCE->value)->count(),
                    'ebay' => DB::table('customer_sales_channels')->where('customer_id', $customer->id)->leftJoin('platforms', 'platforms.id', 'customer_sales_channels.platform_id')->where('platforms.type', PlatformTypeEnum::EBAY->value)->count(),
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
                    'shopify_url' => '.' . config('shopify-app.my_shopify_domain'),
                    'connectRoute' => $customer->shopifyUser ? [
                        'url'       => route('pupil.authenticate')
                    ] : null,
                    'createRoute' => [
                        'name'       => 'retina.dropshipping.platform.shopify_user.store',
                        'parameters' => [],
                        'method'     => 'post'
                    ],
                ],
                'type_manual'   => [
                    'createRoute'       => [
                        'method' => 'post',
                        'name' => match ($customer->is_fulfilment) {
                            true => 'retina.models.fulfilment.customer_sales_channel.manual.store',
                            default => 'retina.models.customer_sales_channel.manual.store',
                        }
                    ]
                ],
                'type_tiktok'   => [

                ],
                'type_woocommerce' => [
                    'connectRoute' => [
                        'name' => 'retina.dropshipping.platform.wc.authorize',
                        'parameters' => [],
                        'method' => 'post'
                    ],
                    'isConnected' => DB::table('customer_sales_channels')->where('customer_id', $customer->id)->leftJoin('platforms', 'platforms.id', 'customer_sales_channels.platform_id')->where('platforms.type', PlatformTypeEnum::WOOCOMMERCE->value)->exists(),
                ],
                'type_ebay' => [
                    'connectRoute' => [
                        'name' => match ($customer->is_fulfilment) {
                            true   => 'retina.fulfilment.dropshipping.customer_sales_channels.ebay.authorize',
                            default => 'retina.dropshipping.platform.ebay.authorize',
                        },
                        'parameters' => [],
                        'method' => 'post'
                    ],
                ],
                'type_amazon' => [
                    'connectRoute' => [
                        'name' => match ($customer->is_fulfilment) {
                            true   => 'retina.fulfilment.dropshipping.customer_sales_channels.amazon.authorize',
                            default => 'retina.dropshipping.platform.amazon.authorize',
                        },
                        'parameters' => [],
                        'method' => 'post'
                    ],
                ],
                'type_magento' => [
                    'connectRoute' => [
                        'name' => match ($customer->is_fulfilment) {
                            true   => 'retina.fulfilment.dropshipping.platform.magento.store', // TODO: Create in fulfilment
                            default => 'retina.dropshipping.platform.magento.store',
                        },
                        'parameters' => [],
                        'method' => 'post'
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs($routeParameters): array
    {
        return
            array_merge(
                IndexDropshippingCustomerSalesChannels::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'retina.dropshipping.customer_sales_channels.create',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Create'),
                        ]
                    ]
                ]
            );
    }
}
