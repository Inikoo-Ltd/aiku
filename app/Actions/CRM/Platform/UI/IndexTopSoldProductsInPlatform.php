<?php

namespace App\Actions\CRM\Platform\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTopSoldProductsInPlatform extends OrgAction
{
    public function handle(Group|Shop $parent, Platform $platform, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('assets.name', $value)
                    ->orWhereStartWith('assets.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(\App\Models\Accounting\InvoiceTransaction::class)
            ->select(
                'assets.id',
                'assets.code',
                'assets.name',
                DB::raw('SUM(invoice_transactions.quantity) as total_sold'),
                DB::raw('SUM(invoice_transactions.'.($parent instanceof Group ? 'grp_net_amount' : 'net_amount').') as total_amount'),
                DB::raw("'" . $parent->currency->code . "' as currency_code")
            )
            ->join('assets', function ($join) {
                $join->on('invoice_transactions.model_id', '=', 'assets.id')
                    ->where('invoice_transactions.model_type', '=', 'Product');
            })
            ->join('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
            ->join('customer_sales_channels', 'invoices.customer_id', '=', 'customer_sales_channels.customer_id')
            ->where('customer_sales_channels.platform_id', $platform->id)
            ->where('assets.type', 'product')
            ->whereNull('invoice_transactions.deleted_at')
            ->groupBy('assets.id', 'assets.code', 'assets.name')
            ->orderByDesc('total_sold');

        if ($parent instanceof Shop) {
            $query->where('invoice_transactions.shop_id', $parent->id);
        }

        return $query
            ->allowedSorts(['total_sold', 'total_amount', 'assets.name', 'assets.code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No top sold products found'),
                    'description' => __('There are no sales recorded for products on this platform.'),
                ])
                ->column(key: 'code', label: __('Code'), sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), sortable: true, searchable: true)
                ->column(key: 'total_sold', label: __('Total Sold'), sortable: true)
                ->column(key: 'total_amount', label: __('Total Amount'), sortable: true)
                ->defaultSort('-total_sold');
        };
    }
}
