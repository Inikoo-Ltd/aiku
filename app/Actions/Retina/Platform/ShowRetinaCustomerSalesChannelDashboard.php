<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-15h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Platform;

use App\Actions\Retina\Dropshipping\CustomerSalesChannel\UI\IndexRetinaDropshippingCustomerSalesChannels;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelConnectionStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\Ordering\Platform\PlatformTypeEnum;

class ShowRetinaCustomerSalesChannelDashboard extends RetinaAction
{
    use GetPlatformLogo;

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

        $step=[];
        $timeline=null;
        if(!$customerSalesChannel->platform_status){

            $timeline= $customerSalesChannel->connection_status !== CustomerSalesChannelConnectionStatusEnum::CONNECTED ? [
                'current_state' => $customerSalesChannel->state->value,
                'options'   => [
                    CustomerSalesChannelConnectionStatusEnum::PENDING->value => [
                        "label" => "Connection Pending",
                        "tooltip" => "Reconnect your account to start using our services",
                        "key" => CustomerSalesChannelConnectionStatusEnum::PENDING->value
                    ],
                    CustomerSalesChannelConnectionStatusEnum::CONNECTED->value => [
                        "label" => "Add products",
                        "tooltip" => "Add products to your portfolio",
                        "key" => CustomerSalesChannelConnectionStatusEnum::CONNECTED->value
                    ]
                ]
                ]
                : null;

            $step= [
                'label' => __('Great! You just complete first step.'),
                'title' => __('Connect your store'),
                'description' => __('Connect your store to Shopify and start selling with ease. Our platform is designed to help you manage your sales channels efficiently, so you can focus on growing your business.'),
                'button' => [
                    'label' => __('Connect your store'),
                    'route_target' => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.reconnect',
                        'parameters' => [
                            'customerSalesChannel' => $customerSalesChannel->slug
                        ],
                        'method'     => 'get',
                    ],
                ],
                'icon' => 'fal fa-link',
            ];
        }





        $renderPage = $customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL ? 'Dropshipping/Platform/PlatformManualDashboard' : 'Dropshipping/Platform/PlatformDashboard';


        $isFulfilment = $this->shop->type == ShopTypeEnum::FULFILMENT;

        return Inertia::render($renderPage, [
            'title'                  => $title,
            'breadcrumbs'            => $this->getBreadcrumbs($customerSalesChannel),
            'pageHead'               => [

                'title' => $customerSalesChannel->name ?? $customerSalesChannel->reference,
                'model' => $customerSalesChannel->platform->name,
                'icon'  => [
                    'icon'  => ['fal', 'fa-code-branch'],
                    'icon_rotation'  => 90,
                    'title' => $title
                ],
                'actions'    => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => $isFulfilment ? 'retina.fulfilment.dropshipping.customer_sales_channels.edit' : 'retina.dropshipping.customer_sales_channels.edit',
                                'parameters' => [
                                    'customerSalesChannel' => $customerSalesChannel->slug,
                                ],
                                'method'     => 'get'
                            ]
                        ]
                ]

            ],
            'timeline' => $timeline,
            'headline'  => [
                'title' => __('Web/API order management'),
                'description' => '<p><span>First, add desired products to your </span><strong>My Products</strong><span> using the </span><strong>Add Products</strong><span> button. When an order comes in, find the client under the </span><strong>Clients</strong><span> tab (add them if new), then click </span><strong>Create Order.</strong><span> Finally, enter product codes and quantities to complete the order.</span></p>'
            ],
            'customer_sales_channel' => $customerSalesChannel,
            'platform'               => $customerSalesChannel->platform,
            'platform_logo'          => $this->getPlatformLogo($customerSalesChannel->platform->code),
            'platformData'           => $this->getPlatformData($customerSalesChannel),
            'can_connect_to_platform' => $customerSalesChannel->can_connect_to_platform,
            'exist_in_platform' => $customerSalesChannel->exist_in_platform,
            'platform_status' => $customerSalesChannel->platform_status,
            'step'  => $step
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
            'label'       => __('Products'),
            'icon'        => 'fal fa-cube',
            'count'       => $customerSalesChannel->number_portfolios,
            'description' => __('total products'),
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
                IndexRetinaDropshippingCustomerSalesChannels::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'retina.dropshipping.customer_sales_channels.show',
                                'parameters' => [$customerSalesChannel->slug]
                            ],
                            'label' => $customerSalesChannel->name.' ('.$customerSalesChannel->platform->type->labels()[$customerSalesChannel->platform->type->value].')',
                        ]
                    ]
                ]
            );
    }
}
