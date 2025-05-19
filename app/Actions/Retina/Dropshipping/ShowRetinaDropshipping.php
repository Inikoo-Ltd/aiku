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
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaDropshipping extends RetinaAction
{
    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisation($request);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $customer = $this->customer;

        $title= __('Sale Channels');

        return Inertia::render(
            'Dropshipping/DropshippingDashboard',
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
                'createRoute' => [
                    'name'       => 'retina.dropshipping.platform.shopify_user.store',
                    'parameters' => [],
                    'method'     => 'post'
                ],
                'unlinkRoute' => [
                    'name'       => 'retina.dropshipping.platform.shopify_user.delete',
                    'parameters' => [],
                    'method'     => 'delete'
                ],
                'fetchCustomerRoute' => [
                    'name'       => 'retina.dropshipping.client.fetch',
                    'parameters' => []
                ],
                'connectRoute' => $customer->shopifyUser ? [
                    'url'       => route('pupil.authenticate', [
                        'shop' => $customer->shopifyUser?->name
                    ])
                ] : null,
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
                'aikuConnectRoute' => [
                    'isAuthenticated' => $customer->customerSalesChannelsXXX()->where('type', PlatformTypeEnum::MANUAL->value)->exists(),
                    'url'       => route('retina.models.dropshipping.aiku.store')
                ],
                'wooRoute' => [
                    'connectRoute' => [
                        'name' => 'retina.dropshipping.platform.wc.authorize',
                        'parameters' => [],
                        'method' => 'post'
                    ],
                    'isConnected' => $customer->customerSalesChannelsXXX()->where('type', PlatformTypeEnum::WOOCOMMERCE->value)->exists()
                ]
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
