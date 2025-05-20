<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-15h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Platform;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaCustomerSalesChannelDashboard extends RetinaAction
{

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->customer_id !== $this->customer->id) {
            return false;
        }

        return true;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): CustomerSalesChannel
    {
        $this->initialisation($request);
        return $customerSalesChannel;
    }

    public function htmlResponse(CustomerSalesChannel $customerSalesChannel): Response
    {

        $title = __('Channel Dashboard');
        return Inertia::render('Dropshipping/Platform/PlatformDashboard', [
            'title'        => $title,
            'breadcrumbs'    => $this->getBreadcrumbs(),
            'pageHead'    => [

                'title'         => $title,
                'icon'          => [
                    'icon'  => ['fal', 'fa-tachometer-alt'],
                    'title' => $title
                ],

            ],



            'amount_shortcuts' => [],

            'platformData'  => $this->getPlatformData($customerSalesChannel),
        ]);
    }

    public function getPlatformData(CustomerSalesChannel $customerSalesChannel): array
    {
        $stats = [];

        $stats['orders'] = [
            'label'         => __('Orders'),
            'count'         => $customerSalesChannel->number_orders,
            'description'   => __('total orders'),
            'route'         => [
                'name' => 'retina.dropshipping.platforms.orders.index'
            ]
        ];

        $stats['clients'] = [
            'label'         => __('Clients'),
            'count'         => $customerSalesChannel->number_customer_clients,
            'description'   => __('total clients'),
            'route'         => [
                'name' => 'retina.dropshipping.platforms.client.index'
            ]
        ];

        $stats['portfolios'] = [
            'label'         => __('Portfolios'),
            'count'         => $customerSalesChannel->number_portfolios,
            'description'   => __('total portfolios'),
            'route'         => [
                'name' => 'retina.dropshipping.platforms.portfolios.index'
            ]
        ];

        return $stats;
    }

    public function getBreadcrumbs(): array
    {

        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.dropshipping.platforms.dashboard',
                                'parameters'  => ['manual']  // TODO: change to correct one
                            ],
                            'label' => __('Channel Dashboard'),
                        ]
                    ]
                ]
            );

    }
}
