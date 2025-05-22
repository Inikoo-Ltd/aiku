<?php

/*
 * author Arya Permana - Kirin
 * created on 21-05-2025-10h-40m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Client\UI;

use App\Actions\Retina\Fulfilment\Dropshipping\WithInCustomerSalesChannelAuthorisation;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaCustomerClientsInCustomerSalesChannel extends RetinaAction
{
    use WithInCustomerSalesChannelAuthorisation;

    private CustomerSalesChannel $customerSalesChannel;


    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('customer_clients.customer_sales_channel_id', $customerSalesChannel->id);

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



    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }



    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
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

        $title = __('Clients');

        $fetchClientLabel = __('Fetch Client');


        $actions = [];

        $platformName = $this->customerSalesChannel->name;

        if ($this->customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL) {
            $platformName = __('Manual');
        }

        if ($this->customerSalesChannel->platform_user_type == 'ShopifyUser') {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => $fetchClientLabel,
                'label'   => $fetchClientLabel,
                'route'   => [
                    'name'       => 'retina.dropshipping.customer_sales_channels.client.fetch',
                    'parameters' => [
                        'customerSalesChannel' => $this->customerSalesChannel->slug
                    ]
                ]
            ];
        }

        if ($this->customerSalesChannel->platform_user_type == 'WooCommerceUser') {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => $fetchClientLabel,
                'label'   => $fetchClientLabel,
                'route'   => [
                    'name'       => 'retina.dropshipping.customer_sales_channels.client.wc-fetch',
                    'parameters' => [
                        'customerSalesChannel' => $this->customerSalesChannel->slug
                    ]
                ]
            ];
        }

        $spreadsheetRoute = null;
        if ($this->customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'create',
                'label' => __('Create Customer Client'),
                'route' => [
                    'name'       => 'retina.dropshipping.customer_sales_channels.client.create',
                    'parameters' => [
                        'customerSalesChannel' => $this->customerSalesChannel->slug
                    ]
                ]
            ];

            $spreadsheetRoute = [
                'event'           => 'action-progress',
                'channel'         => 'grp.personal.'.$this->organisation->group->id,
                'required_fields' => ["contact_name", "company_name", "email", "phone", "address_line_1", "address_line_2", "postal_code", "locality", "country_code"],
                'route'           => [
                    'upload'   => [
                        'name' => 'retina.models.customer_sales_channel.clients.upload',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->id,
                        ],
                    ],
                ],
            ];
        }



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
                    'icon'       => [
                        'icon'  => ['fal', 'fa-user-friends'],
                        'title' => __('customer client')
                    ],
                    'model' => $platformName,
                    'actions'    => $actions
                ],
                'data'        => CustomerClientResource::collection($customerClients),
                'upload_spreadsheet' => $spreadsheetRoute

            ]
        )->table($this->tableStructure());
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
