<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Billables\Charge\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\CRM\CustomersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Billables\Charge;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomersInCharge extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, Charge $charge, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($charge);
    }

    public function handle(Charge $charge, $prefix = null): LengthAwarePaginator
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

        $queryBuilder->where('customers.shop_id', $charge->shop_id);

        $queryBuilder->whereIn('customers.id', function ($query) use ($charge) {
            $query->select('customer_id')
                ->from('transactions')
                ->where('transactions.asset_id', $charge->asset_id)
                ->whereNull('transactions.deleted_at')
                ->distinct();
        });

        return $queryBuilder
            ->defaultSort('-created_at')
            ->select([
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
            ->allowedSorts([
                'reference',
                'name',
                'number_current_customer_clients',
                'number_current_portfolios',
                'slug',
                'created_at',
                'number_invoices_type_invoice',
                'last_invoiced_at',
                'sales_all',
                'number_customer_sales_channels',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Charge $charge, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($charge, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('customer'), __('customers')])
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __("No customers found"),
                    'description' => null,
                    'count' => $charge->stats->customers_invoiced_all ?? 0,
                ])
                ->column(key: 'reference', label: __('Ref'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('Since'), canBeHidden: false, sortable: true, searchable: true, type: 'date')
                ->column(key: 'last_invoiced_at', label: __('Last Invoice'), canBeHidden: false, sortable: true, searchable: true, type: 'date')
                ->column(key: 'number_invoices_type_invoice', label: __('Invoices'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'sales_all', label: __('Sales'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('-created_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return CustomersResource::collection($customers);
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        $charge = $request->route('charge');

        return Inertia::render(
            'Org/Shop/CRM/Customers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $charge,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title' => __('Customers'),
                'pageHead' => [
                    'title' => __('Customers invoiced for :charge', ['charge' => $charge->name]),
                    'icon' => [
                        'icon' => ['fal', 'fa-user'],
                        'title' => __('Customer')
                    ],
                ],
                'data' => CustomersResource::collection($customers),
                'customers' => CustomersResource::collection($customers)
            ]
        )->table($this->tableStructure($charge));
    }

    public function getBreadcrumbs(Charge $charge, string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowCharge::make()->getBreadcrumbs(
                $charge,
                'grp.org.shops.show.billables.charges.show',
                $routeParameters
            ),
            [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Customers'),
                        'icon' => 'fal fa-bars'
                    ],
                ],
            ]
        );
    }
}
