<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 22-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Customer\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Overview\ShowOrganisationOverviewHub;
use App\Actions\Traits\WithCustomersSubNavigation;
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

class IndexCustomersInOverview extends OrgAction
{
    use WithCustomersSubNavigation;

    private Group|Shop|Organisation $parent;

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

    public function handle(Group|Organisation $parent, $prefix = null): LengthAwarePaginator
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
            'platform_name',
        ];

        if ($parent instanceof Group) {
            $queryBuilder->where('customers.group_id', $parent->id)
                ->select([
                    'organisations.name as organisation_name',
                    'organisations.slug as organisation_slug',
                    'customers.organisation_id',
                    'customers.shop_id',
                ])
                ->leftJoin('organisations', 'organisations.id', 'customers.organisation_id');
            $allowedSort = array_merge(['organisation_name', 'shop_name'], $allowedSort);
        } else {
            $queryBuilder->where('customers.organisation_id', $parent->id);
            $allowedSort = array_merge(['shop_name'], $allowedSort);
        }

        return $queryBuilder
            ->defaultSort('-created_at')
            ->addSelect([
                'customers.location',
                'customers.reference',
                'customers.id',
                'customers.name',
                'customers.slug',
                'customer_stats.number_invoices_type_invoice',
                'customers.created_at',
                'shops.name as shop_name',
                'shops.code as shop_code',
                'shops.slug as shop_slug',
                'shops.currency_id',
                'currencies.code as currency_code',
            ])
            ->leftJoin('shops', 'shops.id', 'customers.shop_id')
            ->leftJoin('currencies', 'shops.currency_id', 'currencies.id')
            ->leftJoin('customer_stats', 'customers.id', 'customer_stats.customer_id')
            ->allowedSorts($allowedSort)
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['registered_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Organisation $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->betweenDates(['registered_at']);

            $table
                ->withGlobalSearch()
                ->column(key: 'reference', label: __('ref'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            } else {
                $table->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('since'), canBeHidden: false, sortable: true, searchable: true, type: 'date');

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
        unset($navigation[CustomersTabsEnum::DASHBOARD->value]);

        $this->tab = $request->get('tab', array_key_first($navigation));

        $subNavigation = [];

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
                    'subNavigation' => $subNavigation,
                ]),
                'tabs'                              => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
                CustomersTabsEnum::CUSTOMERS->value => $this->tab == CustomersTabsEnum::CUSTOMERS->value ?
                    fn () => CustomersResource::collection($customers)
                    : Inertia::lazy(fn () => CustomersResource::collection($customers)),

            ]
        )->table($this->tableStructure($this->parent, CustomersTabsEnum::CUSTOMERS->value));
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
            'grp.org.overview.customers.index' =>
            array_merge(
                ShowOrganisationOverviewHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.overview.customers.index',
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
