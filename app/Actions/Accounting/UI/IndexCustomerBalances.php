<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 16 Mar 2023 15:40:54 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\Traits\WithAccountingSubNavigation;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Http\Resources\Accounting\CustomerBalancesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
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

class IndexCustomerBalances extends OrgAction
{
    use WithAccountingSubNavigation;

    private Group|Organisation|Shop|Fulfilment $parent;

    public function handle(Group|Shop|Fulfilment|Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.name', $value)
                    ->orWhereStartWith('customers.email', $value)
                    ->orWhere('customers.reference', '=', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Customer::class);
        $queryBuilder->where('customers.balance', '!=', 0);

        if ($parent instanceof Organisation) {
            $queryBuilder->where('customers.organisation_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $queryBuilder->where('customers.shop_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $queryBuilder->where('customers.group_id', $parent->id);
        } elseif ($parent instanceof Fulfilment) {
            $queryBuilder->where('customers.shop_id', $parent->shop->id);
        }
        $queryBuilder->leftjoin('organisations', 'customers.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftjoin('shops', 'customers.shop_id', 'shops.id');
        $queryBuilder->leftjoin('fulfilments', 'fulfilments.shop_id', 'shops.id');


        return $queryBuilder
            ->defaultSort('customers.slug')
            ->select([
                'customers.id as id',
                'customers.slug as slug',
                'customers.name as name',
                'customers.balance as balance',
                'shops.slug as shop_slug',
                'shops.type as shop_type',
                'fulfilments.slug as fulfilment_slug',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->allowedSorts(['id', 'name', 'slug','balance'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Shop|Organisation|Fulfilment $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    []
                )
                ->column(key: 'name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'balance', label: __('balance'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, searchable: true);
                $table->column(key: 'shop_name', label: __('shop'), canBeHidden: false, searchable: true);
            }
            $table->defaultSort('id');
        };
    }



    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);
        return $this->handle($fulfilment);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
    }

    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return CustomerBalancesResource::collection($customers);
    }


    public function htmlResponse(LengthAwarePaginator $paymentAccounts, ActionRequest $request): Response
    {
        $title = __('Customer Balances');

        $routeName       = $request->route()->getName();
        $routeParameters = $request->route()->originalParameters();

        $subNavigation = [];
        if ($this->parent instanceof Fulfilment) {
            $subNavigation = $this->getSubNavigation($this->parent);
        } elseif ($this->parent instanceof Shop) {
            $subNavigation = $this->getSubNavigationShop($this->parent);
        }
        return Inertia::render(
            'Org/Accounting/CustomerBalances',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                'title'       => $title,

                'pageHead'    => [
                    'icon'      => ['fal', 'fa-money-check-alt'],
                    'title'     => $title,
                    'actions'   => [
                    ],
                    'subNavigation' => $subNavigation,


                ],
                'data'             => CustomerBalancesResource::collection($paymentAccounts)


            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) use ($routeName) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Customer Balances'),
                        'icon'  => 'fal fa-bars',

                    ],

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.accounting.balances.index' =>
            array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb($routeParameters)
            ),
            'grp.overview.accounting.customer-balances.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.shops.show.dashboard.payments.accounting.customer_balances.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeParameters)
            ),
            'grp.org.fulfilments.show.operations.accounting.customer_balances.index' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeParameters)
            ),
            default => []
        };
    }
}
