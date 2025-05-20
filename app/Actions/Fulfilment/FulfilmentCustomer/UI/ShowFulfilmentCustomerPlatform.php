<?php

/*
 * author Arya Permana - Kirin
 * created on 02-04-2025-15h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\FulfilmentCustomer\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerPlatformSubNavigation;
use App\Actions\OrgAction;
use App\Enums\UI\Fulfilment\FulfilmentCustomerPlatformTabsEnum;
use App\Enums\UI\Fulfilment\FulfilmentCustomerTabsEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFulfilmentCustomerPlatform extends OrgAction
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
            'Org/Fulfilment/FulfilmentCustomerPlatform',
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
                    'title'         => $customerSalesChannel->platform->name,
                    'afterTitle'    => [
                        'label' => '('.$customerSalesChannel->customer->name.')',
                    ],
                    'actions'       => $actions
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
            ]
        );
    }

    public function getBreadcrumbs(CustomerSalesChannel $customerSalesChannel, array $routeParameters): array
    {
        $headCrumb = function (Platform $platform, array $routeParameters, string $suffix = '') {
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
                            'label' => $platform->name,
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
                $customerSalesChannel->platform,
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
                            'customerHasPlatform'           => $customerSalesChannel
                        ]
                    ]
                ]
            )
        );
    }
}
