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
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\RetinaCustomerSalesChannelResource;

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

        $step     = [];
        $timeline = null;

        $renderPage = $customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL ? 'Dropshipping/Platform/PlatformManualDashboard' : 'Dropshipping/Platform/PlatformDashboard';

        $isFulfilment = $this->shop->type == ShopTypeEnum::FULFILMENT;

        $canConnectToPlatform = $customerSalesChannel->can_connect_to_platform;
        $existInPlatform = $customerSalesChannel->exist_in_platform;
        $platformStatus = $customerSalesChannel->platform_status;

        if ($customerSalesChannel->status == CustomerSalesChannelStatusEnum::CLOSED) {
            $canConnectToPlatform = false;
            $existInPlatform = false;
            $platformStatus = false;
        }

        return Inertia::render($renderPage, [
            'title'                   => $title,
            'breadcrumbs'             => $this->getBreadcrumbs($customerSalesChannel),
            'pageHead'                => [

                'title'   => $customerSalesChannel->name ?? $customerSalesChannel->reference,
                'model'   => $customerSalesChannel->platform->name,
                'icon'    => [
                    'icon'          => ['fal', 'fa-code-branch'],
                    'icon_rotation' => 90,
                    'title'         => $title
                ],
                'actions' => [
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
            'timeline'                => $timeline,
            'headline'                => [
                'title'       => __('Web/API order management'),
                'description' => '<p><span>First, add desired products to your </span><strong>My Products</strong><span> using the </span><strong>Add Products</strong><span> button. When an order comes in, find the client under the </span><strong>Clients</strong><span> tab (add them if new), then click </span><strong>Create Order.</strong><span> Finally, enter product codes and quantities to complete the order.</span></p>'
            ],
            'portfolios_count'        => $customerSalesChannel->portfolios->count(),
            'customer_sales_channel'  => RetinaCustomerSalesChannelResource::make($customerSalesChannel)->toArray(request()),
            'platform'                => $customerSalesChannel->platform,
            'platform_logo'           => $this->getPlatformLogo($customerSalesChannel->platform->code),
            'platformData'            => $this->getPlatformData($customerSalesChannel),
            'can_connect_to_platform' => $canConnectToPlatform,
            'exist_in_platform'       => $existInPlatform,
            'platform_status'         => $platformStatus,

            'error_captcha' => Arr::get($customerSalesChannel->user->data, 'error_data'),

            'step'                    => $step
        ]);
    }

    public function getPlatformData(CustomerSalesChannel $customerSalesChannel): array
    {
        $stats = [];

        $isFulfilment = $this->shop->type == ShopTypeEnum::FULFILMENT;

        $isManual = $customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL;

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

        if ($isManual) {
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
        }

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
