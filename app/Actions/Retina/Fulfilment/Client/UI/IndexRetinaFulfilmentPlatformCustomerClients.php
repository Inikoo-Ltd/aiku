<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\Client\UI;

use App\Actions\CRM\Customer\UI\IndexCustomerPlatformCustomerClients;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaFulfilmentPlatformCustomerClients extends RetinaAction
{
    /**
     * @var \App\Models\Dropshipping\CustomerSalesChannel
     */
    private CustomerSalesChannel $scope;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->is_root;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = $customerSalesChannel;
        $this->initialisationFromPlatform($customerSalesChannel->platform, $request);

        return IndexCustomerPlatformCustomerClients::run($this->customer, $customerSalesChannel->platform);
    }

    public function inPupil(Platform $platform, ActionRequest $request): LengthAwarePaginator
    {
        $this->asAction = true;
        $this->initialisationFromPupil($request);

        $this->platform = $platform;
        if ($platform->type === PlatformTypeEnum::SHOPIFY) {
            $this->platformUser = $this->customer->shopifyUser;
        } else {
            $this->platformUser = $this->customer->tiktokUser;
        }

        return IndexCustomerPlatformCustomerClients::run($this->customer, $platform);
    }

    public function tableStructure($parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return IndexCustomerPlatformCustomerClients::make()->tableStructure($modelOperations);
    }

    public function jsonResponse(LengthAwarePaginator $customerClients): AnonymousResourceCollection
    {
        return CustomerClientResource::collection($customerClients);
    }

    public function htmlResponse(LengthAwarePaginator $customerClients, ActionRequest $request): Response
    {
        $icon      = ['fal', 'fa-user'];
        $title     = $this->platformUser->name;
        $title = __('Clients');
        $iconRight = [
            'icon'  => ['fal', 'fa-user-friends'],
            'title' => __('customer client')
        ];

        // if ($this->platformUser instanceof TiktokUser) {
        //     $afterTitle = [
        //         'label' => __('Tiktok Clients')
        //     ];
        // } elseif ($this->platformUser instanceof ShopifyUser) {
        //     $afterTitle = [
        //         'label' => __('Shopify Clients')
        //     ];
        // } else {
        //     $afterTitle = [
        //         'label' => __('Clients')
        //     ];
        // }
        $actions = [];
        $shopifyActions = [];
        $shopifyActions = match (class_basename($this->platformUser)) {
            'ShopifyUser' => [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => __('Fetch Client'),
                'label'   => __('Fetch Client'),
                'route'   => [
                    'name'       => 'retina.dropshipping.platforms.client.fetch',
                    'parameters' => [
                        'platform' => $this->platform->slug
                    ]
                ]
            ],
            'WooCommerceUser' => [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => __('Fetch Client'),
                'label'   => __('Fetch Client'),
                'route'   => [
                    'name'       => 'retina.dropshipping.platforms.client.wc-fetch',
                    'parameters' => [
                        'platform' => $this->platform->slug
                    ]
                ]
            ],
            default => []
        };

        $createButton = [];

        if ($this->platform->type == PlatformTypeEnum::MANUAL) {
            $createButton = [
                    'type'  => 'button',
                    'style' => 'create',
                    'label' => __('Create Customer Client'),
                    'route' => [
                        'name'       => 'retina.fulfilment.dropshipping.customer_sales_channels.client.create',
                        'parameters' => [
                            'customerSalesChannel' => $this->scope->slug
                        ]
                    ]
                ];
        }

        $actions = array_merge(
            [$shopifyActions],
            [$createButton]
        );

        $spreadsheetRoute = [
                'event'           => 'action-progress',
                'channel'         => 'grp.personal.'.$this->platform->group->id,
                'required_fields' => ["contact_name", "company_name", "email", "phone", "address_line_1", "address_line_2", "postal_code", "locality", "country_code"],
                'route'           => [
                    'upload'   => [
                        'name' => 'retina.models.customer_sales_channel.clients.upload',
                        'parameters' => [
                            'customerSalesChannel' => $this->scope->id,
                        ],
                    ],
                ],
            ];
        // dd($actions);
        return Inertia::render(
            'Dropshipping/Client/CustomerClients',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters(),
                    $request->route()->getName()
                ),
                'title'       => __('customer clients'),
                'pageHead'    => [
                    'title'      => $title,
                    'model'      => $this->platformUser->name ?? __('Manual'),
                    // 'afterTitle' => $afterTitle,
                    // 'iconRight'  => $iconRight,
                    'icon'       => [
                        'icon'  => ['fal', 'fa-user-friends'],
                        'title' => __('customer client')
                    ],
                    'actions'    => $actions

                ],
                'data'        => CustomerClientResource::collection($customerClients),
                'upload_spreadsheet' => $spreadsheetRoute

            ]
        )->table($this->tableStructure($this->platformUser));
    }

    public function getBreadcrumbs($routeName, $routeParameters): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'retina.dropshipping.platforms.client.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Clients'),
                        ]
                    ]
                ]
            );
    }
}
