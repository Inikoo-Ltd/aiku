<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 May 2025 16:58:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Pupil;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPupilPlatformCustomerClients extends RetinaAction
{
    public function handle(Customer $customer, Platform $platform, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customer_clients.name', $value)
                    ->orWhereStartWith('customer_clients.email', $value)
                    ->orWhere('customer_clients.reference', '=', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(CustomerClient::class);
        $queryBuilder->where('customer_clients.customer_id', $customer->id);
        $queryBuilder->where('customer_clients.platform_id', $platform->id);

        return $queryBuilder
            ->defaultSort('customer_clients.reference')
            ->select([
                'customer_clients.location',
                'customer_clients.reference',
                'customer_clients.id',
                'customer_clients.name',
                'customer_clients.ulid',
                'customer_clients.created_at'
            ])
            ->allowedSorts(['reference', 'name', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

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

        return $this->handle($this->customer, $platform);
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('location'), canBeHidden: false, searchable: true)
                ->column(key: 'created_at', label: __('since'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $customerClients): AnonymousResourceCollection
    {
        return CustomerClientResource::collection($customerClients);
    }

    public function htmlResponse(LengthAwarePaginator $customerClients, ActionRequest $request): Response
    {
        $title            = __('Clients');
        $fetchClientLabel = __('Fetch Client');

        $shopifyActions = match (class_basename($this->platformUser)) {
            'ShopifyUser' => [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => $fetchClientLabel,
                'label'   => $fetchClientLabel,
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
                'tooltip' => $fetchClientLabel,
                'label'   => $fetchClientLabel,
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
                'upload' => [
                    'name'       => 'retina.models.customer-client.platform.upload',
                    'parameters' => [
                        'platform' => $this->platform->id,
                    ],
                ],
            ],
        ];

        return Inertia::render(
            'Dropshipping/Client/CustomerClients',
            [
                'breadcrumbs'        => $this->getBreadcrumbs(
                    $request->route()->originalParameters(),
                ),
                'title'              => __('customer clients'),
                'pageHead'           => [
                    'title'   => $title,
                    'model'   => $this->platformUser->name ?? __('Manual'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-user-friends'],
                        'title' => __('customer client')
                    ],
                    'actions' => $actions

                ],
                'data'               => CustomerClientResource::collection($customerClients),
                'upload_spreadsheet' => $spreadsheetRoute

            ]
        )->table($this->tableStructure());
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
