<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Dropshipping\CustomerSalesChannel\UI\ShowCustomerSalesChannel;
use App\Actions\Dropshipping\CustomerSalesChannel\UI\WithCustomerSalesChannelSubNavigation;
use App\Actions\Dropshipping\WithDropshippingAuthorisation;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\UI\ShowFulfilmentCustomerPlatform;
use App\Actions\Fulfilment\WithFulfilmentCustomerPlatformSubNavigation;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomerClients extends OrgAction
{
    use WithCustomerSubNavigation;
    use WithFulfilmentCustomerSubNavigation;
    use WithDropshippingAuthorisation;
    use WithFulfilmentCustomerPlatformSubNavigation;
    use WithCustomerSalesChannelSubNavigation;

    private Customer|FulfilmentCustomer|CustomerSalesChannel $parent;

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent        = $customerSalesChannel;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customerSalesChannel);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilmentCustomer);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomerSalesChannelInFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customerSalesChannel;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($customerSalesChannel);
    }


    public function handle(Customer|FulfilmentCustomer|CustomerSalesChannel $parent, $prefix = null): LengthAwarePaginator
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

        if ($parent instanceof FulfilmentCustomer) {
            $queryBuilder = $queryBuilder->where('customer_id', $parent->customer_id);
        } elseif ($parent instanceof CustomerSalesChannel) {
            $queryBuilder = $queryBuilder->where('customer_sales_channel_id', $parent->id);
        } else { // Customer
            $queryBuilder->where('customer_clients.customer_id', $parent->id);
        }


        return $queryBuilder
            ->defaultSort('customer_clients.reference')
            ->select([
                'customer_clients.location',
                'customer_clients.reference',
                'customer_clients.id',
                'customer_clients.name',
                'customer_clients.ulid',
                'customers.reference as customer_reference',
                'customers.slug as customer_slug',
                'customer_clients.created_at'
            ])
            ->leftJoin('customers', 'customers.id', 'customer_id')
            ->allowedSorts(['reference', 'name', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Customer|FulfilmentCustomer|CustomerSalesChannel $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Customer' => [
                            'title'       => __("No clients found"),
                            'description' => __("You can add your client 🤷🏽‍♂️"),
                            'count'       => $parent->stats->number_customer_clients,
                            'action'      => [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('new client'),
                                'label'   => __('client'),
                                'route'   => match (class_basename($parent)) {
                                    'Customer' => [
                                        'name'       => 'grp.org.shops.show.crm.customers.show.customer_clients.create',
                                        'parameters' => request()->route()->originalParameters()
                                    ],
                                    'FulfilmentCustomer' => [
                                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_clients.index',
                                        'parameters' => request()->route()->originalParameters()
                                    ],
                                    default => null
                                }
                            ]
                        ],
                        default => null
                    }
                )
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

        $icon       = ['fal', 'fa-user'];
        $iconRight  = [
            'icon'  => ['fal', 'fa-user-friends'],
            'title' => __('customer client')
        ];
        $afterTitle = [

            'label' => __('Clients')
        ];

        $title = __('Clients');
        if ($this->parent instanceof FulfilmentCustomer) {
            $scope         = $this->parent->customer;
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($scope->fulfilmentCustomer, $request);
            $title      = $scope->name;

        } elseif ($this->parent instanceof CustomerSalesChannel && $this->shop->type == ShopTypeEnum::FULFILMENT) {
            $scope         = $this->parent->customer;
            $subNavigation = $this->getFulfilmentCustomerPlatformSubNavigation($this->parent, $request);
        } elseif ($this->parent instanceof CustomerSalesChannel && $this->shop->type == ShopTypeEnum::DROPSHIPPING) {

            $scope         = $this->parent->customer;
            $subNavigation = $this->getCustomerPlatformSubNavigation($this->parent, $request);
            $title         = $this->parent->customer->name.' ('.$this->parent->customer->reference.')';
            $afterTitle    = [
                'label' => __('Clients').' @'.$this->parent->platform->name,
            ];
        } else {
            $scope         = $this->parent;
            $subNavigation = $this->getCustomerDropshippingSubNavigation($scope, $request);
            $title      = $scope->name;

        }



        $newClientLabel = __('New Client');

        return Inertia::render(
            'Org/Shop/CRM/CustomerClients',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('customer clients'),


                'pageHead' => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => [
                        $this->parent instanceof CustomerSalesChannel
                            ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => $newClientLabel,
                            'label'   => $newClientLabel,
                            'route'   => [
                                'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.create',
                                'parameters' => [
                                    'organisation' => $scope->organisation->slug,
                                    'shop'         => $scope->shop->slug,
                                    'customer'     => $scope->slug,
                                    'platform'     => $this->parent->platform->slug
                                ]
                            ]
                        ]
                            : [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => $newClientLabel,
                            'label'   => $newClientLabel,
                            'route'   => [
                                'name'       => 'grp.org.shops.show.crm.customers.show.customer_clients.create',
                                'parameters' => [
                                    'organisation' => $scope->organisation->slug,
                                    'shop'         => $scope->shop->slug,
                                    'customer'     => $scope->slug
                                ]
                            ]
                        ],
                    ],

                ],
                'data'     => CustomerClientResource::collection($customerClients),

            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(Customer|FulfilmentCustomer|CustomerSalesChannel $parent, string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Clients'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };



        return match ($routeName) {
            'grp.org.shops.show.crm.customers.show.customer_clients.index' =>
            array_merge(
                ShowCustomer::make()->getBreadcrumbs(
                    'grp.org.shops.show.crm.customers.show',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.customers.show.customer_clients.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.fulfilments.show.crm.customers.show.customer_clients.index' =>
            array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_clients.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                    ]
                )
            ),
            'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.index',
            'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show' =>
            array_merge(
                ShowFulfilmentCustomerPlatform::make()->getBreadcrumbs(
                    $parent,
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer', 'customerSalesChannel'])
                    ]
                )
            ),
            'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.index',
            'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show' =>
            array_merge(
                ShowCustomerSalesChannel::make()->getBreadcrumbs(
                    $parent,
                    $routeName,
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'customer', 'customerSalesChannel'])
                    ]
                )
            ),
            default => []
        };
    }
}
