<?php

namespace App\Actions\CRM\Customer\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IndexTopSoldProductsInCountry extends OrgAction
{
    public function handle(Shop $shop, string $countryCode, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(InvoiceTransaction::class)
            ->select(
                'assets.id',
                'assets.slug',
                'assets.code',
                'assets.name',
                DB::raw('SUM(invoice_transactions.quantity) as total_sold'),
                DB::raw('SUM(invoice_transactions.net_amount) as total_amount'),
                DB::raw("'" . $shop->currency->code . "' as currency_code")
            )
            ->join('assets', function ($join) {
                $join->on('invoice_transactions.model_id', '=', 'assets.id')
                    ->where('invoice_transactions.model_type', '=', 'Product');
            })
            ->join('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->where('invoice_transactions.shop_id', $shop->id)
            ->whereRaw("customers.location->>0 = ?", [$countryCode])
            ->where('assets.type', 'product')
            ->whereNull('invoice_transactions.deleted_at')
            ->groupBy('assets.id', 'assets.slug', 'assets.code', 'assets.name')
            ->orderByDesc('total_sold')
            ->allowedSorts(['total_sold', 'total_amount', 'assets.name', 'assets.code'])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('product'), __('products')])
                ->withEmptyState([
                    'title'       => __('No products sold'),
                    'description' => __('No sales recorded for products in this country.'),
                ])
                ->betweenDates(['date'])
                ->column(key: 'code', label: __('Code'), sortable: true)
                ->column(key: 'name', label: __('Name'), sortable: true)
                ->column(key: 'total_sold', label: __('Qty Sold'), sortable: true)
                ->column(key: 'total_amount', label: __('Revenue'), sortable: true)
                ->defaultSort('-total_sold');
        };
    }
}
