<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\UI\CRM\CustomersTabsEnum;
use App\Http\Resources\CRM\CustomersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomers extends OrgAction
{
    use WithCustomersSubNavigation;
    use WithCRMAuthorisation;

    private Group|Shop|Organisation $parent;


    protected function getElementGroups($parent): array
    {
        return [
            'state'  => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    CustomerStateEnum::labels(),
                    CustomerStateEnum::count($parent)
                ),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('customers.state', $elements);
                }
            ],
            'status' => [
                'label'    => __('Status'),
                'elements' => array_merge_recursive(
                    CustomerStatusEnum::labels(),
                    CustomerStatusEnum::count($parent)
                ),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('customers.status', $elements);
                }
            ]
        ];
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(CustomersTabsEnum::values());

        return $this->handle($this->parent);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request)->withTab(CustomersTabsEnum::values());

        return $this->handle($this->parent);
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(CustomersTabsEnum::values());

        return $this->handle($shop, CustomersTabsEnum::CUSTOMERS->value);
    }


    public function handle(Group|Organisation|Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($parent) {
            $query->where(function ($query) use ($value, $parent) {
                $query->whereAnyWordStartWith('customers.name', $value)
                    ->orWhereStartWith('customers.email', $value)
                    ->orWhere('customers.reference', '=', $value);
                if (class_basename($parent) == 'Group') {
                    $query->orWhereStartWith('organisations.name', $value);
                    $query->orWhereStartWith('shops.name', $value);
                }
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Customer::class);

        if ($parent instanceof Organisation || $parent instanceof Shop) {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }


        $allowedSort = [
            'reference',
            'name',
            'number_current_customer_clients',
            'number_current_portfolios',
            'slug',
            'created_at',
            'number_invoices_type_invoice',
            'last_invoiced_at',
            'sales_all',
            'invoiced_org_net_amount',
            'invoiced_grp_net_amount',
            'number_customer_sales_channels',
        ];


        if (class_basename($parent) == 'Shop') {
            $queryBuilder->where('customers.shop_id', $parent->id);
        } elseif (class_basename($parent) == 'Group') {
            $queryBuilder->where('customers.group_id', $parent->id)
                ->select([
                    'shops.name as shop_name',
                    'shops.slug as shop_slug',
                    'organisations.name as organisation_name',
                    'organisations.slug as organisation_slug',
                    'customers.organisation_id',
                    'customers.shop_id',
                ])
                ->leftJoin('organisations', 'organisations.id', 'customers.organisation_id');
            $allowedSort = array_merge(['organisation_name', 'shop_name'], $allowedSort);
        } else {
            $queryBuilder->where('customers.organisation_id', $parent->id)
                ->select([
                    'shops.code as shop_code',
                    'shops.slug as shop_slug',
                ])
                ->leftJoin('shops', 'shops.id', 'shop_id');
        }

        return $queryBuilder
            ->defaultSort('-created_at')
            ->addSelect([
                'customers.location',
                'customers.reference',
                'customers.id',
                'customers.name',
                'customers.slug',
                'customers.created_at',
                'customer_stats.number_current_portfolios',
                'customer_stats.number_current_customer_clients',
                'customer_stats.last_invoiced_at',
                'customer_stats.number_invoices_type_invoice',
                'customer_stats.sales_all',
                'customer_stats.sales_org_currency_all',
                'customer_stats.sales_grp_currency_all',
                'shops.currency_id',
                'number_customer_sales_channels',
                'currencies.code as currency_code',
            ])
            ->leftJoin('customer_stats', 'customers.id', 'customer_stats.customer_id')
            ->leftJoin('shops', 'customers.shop_id', 'shops.id')
            ->leftJoin('currencies', 'shops.currency_id', 'currencies.id')
            ->allowedSorts($allowedSort)
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['registered_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Organisation|Shop $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            if (!($parent instanceof Group)) {
                foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            $table->betweenDates(['registered_at']);

            $isDropshipping = false;
            if ($parent instanceof Shop && $parent->type == ShopTypeEnum::DROPSHIPPING) {
                $isDropshipping = true;
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Shop' => [
                            'title'       => __("No customers found"),
                            'description' => ($parent->type == ShopTypeEnum::FULFILMENT || $parent->type == ShopTypeEnum::DROPSHIPPING) ? __("You can add your customer 🤷🏽‍♂️") : null,
                            'count'       => $parent->crmStats->number_customers,
                            'action'      => ($parent->type == ShopTypeEnum::FULFILMENT || $parent->type == ShopTypeEnum::DROPSHIPPING) ? [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('new customer'),
                                'label'   => __('customer'),
                                'route'   => [
                                    'name'       => 'grp.org.shops.show.crm.customers.create',
                                    'parameters' => [
                                        'organisation' => $parent->organisation->slug,
                                        'shop'         => $parent->slug
                                    ]
                                ]
                            ] : null
                        ],
                        default => null
                    }
                )
                ->column(key: 'reference', label: __('ref'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            } else {
                $table->column(key: 'location', label: __('location'), canBeHidden: false, searchable: true);
            }

            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('since'), canBeHidden: false, sortable: true, searchable: true, type: 'date');


            if ($isDropshipping) {
                $table->column(key: 'number_current_customer_clients', label: '', icon: 'fal fa-users', tooltip: __('Clients'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'number_current_portfolios', label: '', icon: 'fal fa-chess-board', tooltip: __('Portfolios'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'number_customer_sales_channels', label: __('Channels'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'last_invoiced_at', label: __('last invoice'), canBeHidden: false, sortable: true, searchable: true, type: 'date')
                ->column(key: 'number_invoices_type_invoice', label: __('invoices'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'sales_all', label: __('sales'), canBeHidden: false, sortable: true, searchable: true);

            $table->defaultSort('-created_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return CustomersResource::collection($customers);
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        $navigation = CustomersTabsEnum::navigation();
        if ($this->parent instanceof Group) {
            unset($navigation[CustomersTabsEnum::DASHBOARD->value]);
            $this->tab = $request->get('tab', array_key_first($navigation));
        }

        $subNavigation = [];
        if ($this->parent instanceof Shop) {
            $subNavigation = $this->getSubNavigation($request);
        }
        $scope = $this->parent;

        $action = null;

        if (!$scope instanceof Group && $this->canEdit) {
            $action = [
                [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('New Customer'),
                    'label'   => __('New Customer'),
                    'route'   => [
                        'name'       => 'grp.org.shops.show.crm.customers.create',
                        'parameters' => [
                            'organisation' => $scope->organisation->slug,
                            'shop'         => $scope->slug
                        ]
                    ]
                ],
            ];
        }


        return Inertia::render(
            'Org/Shop/CRM/Customers',
            [
                'breadcrumbs'                       => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'                             => __('customers'),
                'pageHead'                          => array_filter([
                    'title'         => __('customers'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('customer')
                    ],
                    'actions'       => $action,
                    'subNavigation' => $subNavigation,
                ]),
                'data'                              => CustomersResource::collection($customers),
                'tabs'                              => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
                CustomersTabsEnum::DASHBOARD->value => $this->tab == CustomersTabsEnum::DASHBOARD->value ?
                    fn () => GetCustomersDashboard::run($this->parent, $request)
                    : Inertia::lazy(fn () => GetCustomersDashboard::run($this->parent, $request)),
                CustomersTabsEnum::CUSTOMERS->value => $this->tab == CustomersTabsEnum::CUSTOMERS->value ?
                    fn () => CustomersResource::collection($customers)
                    : Inertia::lazy(fn () => CustomersResource::collection($customers)),

            ]
        )->table($this->tableStructure(parent: $this->parent, prefix: CustomersTabsEnum::CUSTOMERS->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Customers'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.crm.customers.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.customers.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.overview.crm.customers.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => 'grp.overview.crm.customers.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
