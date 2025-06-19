<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-15h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Platform;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

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

        $stepLabel = __('Great! You just complete first step.');
        $stepLabel = __('Almost! Setup credit card to make you easier in the future.');
        $stepLabel = __('Very very last! Add products to your store.');

        $stepTitle = __('Connect your store');
        $stepTitle = __('Setup your credit card');
        $stepTitle = __('Add products to your store');

        $stepDescription = __('Connect your store to Shopify and start selling with ease. Our platform is designed to help you manage your sales channels efficiently, so you can focus on growing your business.');
        $stepDescription = __('To manage your payment methods. If you mind to do it later, you can skip this step.');
        $stepDescription = __('Add products to your store to start selling. Select items from our catalogue or upload your own products to showcase in your sales channel.');

        $stepButton = [
            'label'       => __('Connect your store'),
            'route_target' => [
                'name'       => 'retina.dropshipping.customer_sales_channels.index',
            ],
        ];

        $stepButton = [
            'label'       => __('Setup credit card'),
            'route_target' => [
                'name'       => 'retina.dropshipping.mit_saved_cards.create',
            ],
        ];

        $stepButton = [
            'label'       => __('Add portfolios'),
            'route_target' => [
                'name'       => 'retina.dropshipping.customer_sales_channels.portfolios.index',
                'parameters' => [
                    'customerSalesChannel' => $customerSalesChannel->slug,
                ]
            ],
        ];

        $stepIcon = 'fal fa-link';
        $stepIcon = 'fal fa-cube';
        $stepIcon = 'fal fa-credit-card';

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
            'timeline' => $customerSalesChannel->state !== CustomerSalesChannelStateEnum::READY ? [
                'current_state' => $customerSalesChannel->state->value,
                'options'   => [
                    CustomerSalesChannelStateEnum::CREATED->value => [
                        "label" => "Account Created",
                        "tooltip" => "Create account to connect",
                        "key" => CustomerSalesChannelStateEnum::CREATED->value
                    ],
                    CustomerSalesChannelStateEnum::AUTHENTICATED->value => [
                        "label" => "Connected",
                        "tooltip" => "Connect to platform to able receive orders",
                        "key" => CustomerSalesChannelStateEnum::AUTHENTICATED->value
                    ],
                    CustomerSalesChannelStateEnum::CARD_SAVED->value => [
                        "label" => "Setup card",
                        "tooltip" => "Setup cards to make a payment",
                        "key" => CustomerSalesChannelStateEnum::CARD_SAVED->value
                    ],
                    CustomerSalesChannelStateEnum::PORTFOLIO_ADDED->value => [
                        "label" => "Add products",
                        "tooltip" => "Add products to your portfolio",
                        "key" => CustomerSalesChannelStateEnum::PORTFOLIO_ADDED->value
                    ]
                ],
            ] : null,
            'customer_sales_channel' => $customerSalesChannel,
            'platform'               => $customerSalesChannel->platform,
            'platform_logo'          => $this->getPlatformLogo($customerSalesChannel),
            'platformData'           => $this->getPlatformData($customerSalesChannel),
            'step'  => [
                'label'         => $stepLabel,
                'title'         => $stepTitle,
                'description'   => $stepDescription,
                'button'        => $stepButton,
                'icon'          => $stepIcon,
            ]
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
