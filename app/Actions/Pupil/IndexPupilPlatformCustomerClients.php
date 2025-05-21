<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 May 2025 16:58:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Pupil;

use App\Actions\CRM\Customer\UI\IndexCustomerPlatformCustomerClients;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Models\Dropshipping\Platform;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexPupilPlatformCustomerClients extends RetinaAction
{
    public function asController(Platform $platform, ActionRequest $request): LengthAwarePaginator
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

        $title = __('Clients');

        $shopifyActions = match (class_basename($this->platformUser)) {
            'ShopifyUser' => [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => __('Fetch Client'),
                'label'   => __('Fetch Client'),
                'route'   => [
                    'name'       => $this->asPupil ? 'pupil.dropshipping.platforms.client.fetch' : 'retina.dropshipping.customer_sales_channels.client.fetch',
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
                    'name'       => 'retina.dropshipping.customer_sales_channels.client.wc-fetch',
                    'parameters' => [
                        'platform' => $this->platform->slug
                    ]
                ]
            ],
            default => []
        };

        $createButton = [];

        if ($this->shop->type != ShopTypeEnum::FULFILMENT) {
            $createButton = [
                    'type'  => 'button',
                    'style' => 'create',
                    'label' => __('Create Customer Client'),
                    'route' => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.client.create',
                        'parameters' => [
                            'platform' => $this->platform->slug
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
                        'name' => 'retina.models.customer-client.platform.upload',
                        'parameters' => [
                            'platform' => $this->platform->id,
                        ],
                    ],
                ],
            ];
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
                                'name'       => 'retina.dropshipping.customer_sales_channels.client.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Clients'),
                        ]
                    ]
                ]
            );
    }
}
