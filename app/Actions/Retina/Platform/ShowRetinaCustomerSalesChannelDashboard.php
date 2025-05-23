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
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaCustomerSalesChannelDashboard extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }

        return false;
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
            'title'                  => $title,
            'breadcrumbs'            => $this->getBreadcrumbs($customerSalesChannel),
            'pageHead'               => [

                'title' => $title,
                'icon'  => [
                    'icon'  => ['fal', 'fa-tachometer-alt'],
                    'title' => $title
                ],

            ],
            'timeline' => [  // TODO
                'current_state' => 'submitted',
                'options'   => [
                    "in_process" => [
                        "label" => "Get started",
                        "tooltip" => "In Process",
                        "key" => "in_process",
                        "timestamp" => "2025-01-20T10:56:58.000000Z"
                    ],
                    "submitted" => [
                        "label" => "Add products",
                        "tooltip" => "Add products to your portfolio",
                        "key" => "submitted",
                        "timestamp" => "2025-01-22T07:31:44.000000Z"
                    ],
                    "confirmed" => [
                        "label" => "Setup card",
                        "tooltip" => "Confirmed",
                        "key" => "confirmed",
                        // "timestamp" => "2025-01-22T07:31:45.000000Z"
                    ],
                    "booked_in" => [
                        "label" => "Finish",
                        "tooltip" => "Booked In",
                        "key" => "booked_in",
                        // "timestamp" => "2025-01-22T13:45:30.000000Z"
                    ]
                ],
            ],
            'customer_sales_channel' => $customerSalesChannel,
            'platform'               => $customerSalesChannel->platform,
            'platformData'           => $this->getPlatformData($customerSalesChannel),
        ]);
    }

    public function getPlatformData(CustomerSalesChannel $customerSalesChannel): array
    {
        $stats = [];

        $isFulfilment = $this->shop->type == ShopTypeEnum::FULFILMENT;

        $stats['orders'] = [
            'label'       => __('Orders'),
            'icon'        => 'fal fa-shopping-cart',
            'count'       => $customerSalesChannel->number_orders,
            'description' => __('total orders'),
            'route'       => [
                'name'       => $isFulfilment ? 'retina.fulfilment.dropshipping.customer_sales_channels.orders.index' : 'retina.dropshipping.customer_sales_channels.orders.index',
                'parameters' => [
                    'customerSalesChannel' => $customerSalesChannel->slug,
                ]
            ]
        ];

        $stats['clients'] = [
            'label'       => __('Clients'),
            'icon'        => 'fal fa-user-friends',
            'count'       => $customerSalesChannel->number_customer_clients,
            'description' => __('total clients'),
            'route'       => [
                'name'       => $isFulfilment ? 'retina.fulfilment.dropshipping.customer_sales_channels.client.index' : 'retina.dropshipping.customer_sales_channels.client.index',
                'parameters' => [
                    'customerSalesChannel' => $customerSalesChannel->slug,
                ]
            ]
        ];

        $stats['portfolios'] = [
            'label'       => __('Portfolios'),
            'icon'        => 'fal fa-cube',
            'count'       => $customerSalesChannel->number_portfolios,
            'description' => __('total portfolios'),
            'route'       => [
                'name'       => $isFulfilment ? 'retina.fulfilment.dropshipping.customer_sales_channels.portfolios.index' : 'retina.dropshipping.customer_sales_channels.portfolios.index',
                'parameters' => [
                    'customerSalesChannel' => $customerSalesChannel->slug,
                ]
            ]
        ];

        return $stats;
    }

    public function getBreadcrumbs(CustomerSalesChannel $customerSalesChannel): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'retina.dropshipping.customer_sales_channels.show',
                                'parameters' => [$customerSalesChannel->slug]
                            ],
                            'label' => __('Channel Dashboard'),
                        ]
                    ]
                ]
            );
    }
}
