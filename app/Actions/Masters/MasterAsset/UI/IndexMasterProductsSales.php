<?php

/*
 * author Louis Perez
 * created on 24-11-2025-15h-42m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use App\Models\Accounting\InvoiceTransaction;
use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterProductsSales extends OrgAction
{
    public function handle(MasterAsset $masterAsset, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('invoices.reference', 'ilike', "%{$value}%")
                    ->orWhere('customers.name', 'ilike', "%{$value}%");
            });
        });

        $modelId = $masterAsset->products ? $masterAsset->products->pluck('id') : [];

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $morphType = (new Product())->getMorphClass();

        $query = QueryBuilder::for(InvoiceTransaction::class)
            ->join('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'invoices.currency_id')
            ->leftJoin('shops', 'shops.id', '=', 'invoices.shop_id')
            ->leftJoin('organisations', 'organisations.id', '=', 'invoices.organisation_id')
            ->leftJoin('products', 'products.id', 'invoice_transactions.model_id')
            ->where('invoices.in_process', false)
            ->where('invoices.type', InvoiceTypeEnum::INVOICE)
            ->where('invoice_transactions.model_type', $morphType)
            ->whereIn('invoice_transactions.model_id', $modelId)
            ->select([
                'invoices.id',
                'invoices.slug',
                'invoices.reference',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'invoices.date',
                'invoices.pay_status',
                'currencies.code as currency_code',
                'shops.slug as shop_slug',
                'organisations.slug as organisation_slug',
                'invoice_transactions.model_id as product_id',
                DB::raw('SUM(invoice_transactions.net_amount) as total_sales'),
                'products.asset_id as product_asset',
                DB::raw("'$masterAsset->code' as product_code")
            ])
            ->groupBy(
                'invoices.id',
                'invoices.slug',
                'invoices.reference',
                'customers.name',
                'customers.slug',
                'invoices.date',
                'invoices.pay_status',
                'currencies.code',
                'shops.slug',
                'organisations.slug',
                'invoice_transactions.model_id',
                'products.asset_id'
            );

        return $query
        ->defaultSort('-date')
        ->allowedSorts(['reference', 'product_asset', 'customer_name', 'date', 'pay_status', 'total_sales'])
        ->allowedFilters([$globalSearch])
        ->withPaginator($prefix, tableName: request()->route()->getName())
        ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->column('reference', __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column('product_asset', __('Product'), canBeHidden: false, sortable: false, searchable: false)
                ->column('customer_name', __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column('date', __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column('pay_status', __('Payment'), canBeHidden: false, sortable: true, searchable: false, type: 'icon')
                ->column('total_sales', __('Total Sales'), canBeHidden: false, sortable: true, type: 'number')
                ->defaultSort('-date');
        };
    }
}
