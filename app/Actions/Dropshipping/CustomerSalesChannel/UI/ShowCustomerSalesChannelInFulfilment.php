<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 May 2025 12:58:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerPlatformSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\UI\Fulfilment\FulfilmentCustomerPlatformTabsEnum;
use App\Enums\UI\Fulfilment\FulfilmentCustomerTabsEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCustomerSalesChannelInFulfilment extends OrgAction
{
    use WithFulfilmentCustomerPlatformSubNavigation;

    public function handle(CustomerSalesChannel $customerSalesChannel): CustomerSalesChannel
    {
        return $customerSalesChannel;
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, CustomerSalesChannel $customerSalesChannel, ActionRequest $request): CustomerSalesChannel
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(FulfilmentCustomerTabsEnum::values());
        return $this->handle($customerSalesChannel);
    }

    public function htmlResponse(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Response
    {
        $navigation = FulfilmentCustomerPlatformTabsEnum::navigation();

        $actions = [];


        return Inertia::render(
            'Org/Fulfilment/CustomerSalesChannelInFulfilment',
            [
                'title'       => __('customer'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $customerSalesChannel,
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'          => [
                        'title' => __('platform'),
                        'icon'  => 'fal fa-user',
                    ],
                    'model'         => __('Platform'),
                    'subNavigation' => $this->getFulfilmentCustomerPlatformSubNavigation($customerSalesChannel, $request),
                    'title'         => $customerSalesChannel->reference,
                    'afterTitle'    => [
                        'label' => '('.$customerSalesChannel->customer->name.')',
                    ],
                    'actions'       => $actions
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],

                'showcase' => [
                    'stats' => [
                        'name' => match ($customerSalesChannel->platform->type) {
                            PlatformTypeEnum::SHOPIFY => $customerSalesChannel->customer->shopifyUser->name,
                            PlatformTypeEnum::WOOCOMMERCE => $customerSalesChannel->customer->wooCommerceUser->name,
                            PlatformTypeEnum::TIKTOK => $customerSalesChannel->customer->tiktokUser->name,
                            default => $customerSalesChannel->customer->name,
                        },
                        'number_orders' => $customerSalesChannel->number_orders,
                        'number_customer_clients' => $customerSalesChannel->number_customer_clients,
                        'number_portfolios' => $customerSalesChannel->number_portfolios
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(CustomerSalesChannel $customerSalesChannel, array $routeParameters): array
    {
        $headCrumb = function (CustomerSalesChannel $customerSalesChannel, array $routeParameters, string $suffix = '') {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Channels')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $customerSalesChannel->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };


        return array_merge(
            ShowFulfilmentCustomer::make()->getBreadcrumbs(
                $routeParameters
            ),
            $headCrumb(
                $customerSalesChannel,
                [

                    'index' => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer', 'platform'])
                    ],
                    'model' => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show',
                        'parameters' => [
                            'organisation'       => $routeParameters['organisation'],
                            'fulfilment'         => $routeParameters['fulfilment'],
                            'fulfilmentCustomer' => $routeParameters['fulfilmentCustomer'],
                            'customerSalesChannel'           => $customerSalesChannel
                        ]
                    ]
                ]
            )
        );
    }
}
