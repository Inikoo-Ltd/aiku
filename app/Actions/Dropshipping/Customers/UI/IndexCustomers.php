<?php

namespace App\Actions\Dropshipping\Customers\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomers extends OrgAction
{
    /**
     * Handle listing customers under a given Shop and Platform.
     *
     * @param Shop $shop
     * @param Platform $platform
     * @param string|null $prefix
     * @return LengthAwarePaginator
     */
    public function handle(Shop $shop, Platform $platform, ?string $prefix = null): LengthAwarePaginator
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

        $query = QueryBuilder::for(CustomerSalesChannel::class)
            ->join('customers', 'customer_sales_channels.customer_id', '=', 'customers.id')
            ->leftJoin('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
            ->leftJoin('shops', 'customers.shop_id', '=', 'shops.id')
            ->leftJoin('currencies', 'shops.currency_id', '=', 'currencies.id')
            ->where('customer_sales_channels.platform_id', $platform->id)
            ->where('customers.shop_id', $shop->id)
            ->select([
                'customers.id',
                'customers.name',
                'customers.email',
                'customers.reference',
                'customers.registered_at',
                'customers.created_at',
                'customers.location',
                'customer_stats.number_current_portfolios',
                'customer_stats.number_current_customer_clients',
                'customer_stats.last_invoiced_at',
                'customer_stats.number_invoices_type_invoice',
                'customer_stats.sales_all',
                'customer_stats.sales_org_currency_all',
                'shops.currency_id',
                'currencies.code as currency_code',
            ])
            ->groupBy([
                'customers.id',
                'customers.name',
                'customers.email',
                'customers.reference',
                'customers.registered_at',
                'customers.created_at',
                'customers.location',
                'customer_stats.number_current_portfolios',
                'customer_stats.number_current_customer_clients',
                'customer_stats.last_invoiced_at',
                'customer_stats.number_invoices_type_invoice',
                'customer_stats.sales_all',
                'customer_stats.sales_org_currency_all',
                'shops.currency_id',
                'currencies.code',
            ]);

        $allowedSorts = [
            'name',
            'email',
            'reference',
            'registered_at',
            'created_at',
            'sales_all',
            'last_invoiced_at',
            'number_invoices_type_invoice',
            'number_current_customer_clients',
            'number_current_portfolios',
        ];

        return $query
            ->defaultSort('-created_at')
            ->allowedSorts($allowedSorts)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * Table layout definition (InertiaTable)
     *
     * @param array|null $modelOperations
     * @param string|null $prefix
     * @return Closure
     */
    public function tableStructure(?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->betweenDates(['registered_at'])
                ->withEmptyState([
                    'title'       => __("No customers found"),
                    'description' => __("You can import or add customers for this platform."),
                ])
                ->column(key: 'reference', label: __('Ref'), sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), searchable: true)
                ->column(key: 'name', label: __('Name'), sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('Since'), sortable: true, type: 'date')
                ->column(key: 'number_current_customer_clients', label: '', icon: 'fal fa-users', tooltip: __('Clients'), sortable: true)
                ->column(key: 'number_current_portfolios', label: '', icon: 'fal fa-chess-board', tooltip: __('Portfolio'), sortable: true)
                ->column(key: 'number_invoices_type_invoice', label: __('Invoices'), sortable: true)
                ->column(key: 'last_invoiced_at', label: __('Last Invoice'), sortable: true, type: 'date')
                ->column(key: 'sales_all', label: __('Sales'), sortable: true)
                ->defaultSort('-created_at');
        };
    }
}
