<?php

/*
 * author Louis Perez
 * created on 28-11-2025-09h-31m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Masters\MasterProductCategory;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterFamilySales extends OrgAction
{
    public function handle(MasterProductCategory $masterFamily, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('invoices.reference', 'like', "%{$value}%")
                    ->orWhere('customers.name', 'like', "%{$value}%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $familyIds = $masterFamily->productCategories->pluck('id');

        $query = QueryBuilder::for(InvoiceTransaction::class)
            ->join('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'invoices.currency_id')
            ->leftJoin('shops', 'shops.id', '=', 'invoices.shop_id')
            ->leftJoin('organisations', 'organisations.id', '=', 'invoices.organisation_id')
            ->where('invoices.in_process', false)
            ->where('invoices.type', InvoiceTypeEnum::INVOICE)
            ->whereIn('invoice_transactions.family_id', $familyIds)
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
                'invoice_transactions.family_id',
                DB::raw('SUM(invoice_transactions.net_amount) AS total_sales'),
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
                'invoice_transactions.family_id'
            );

        return $query
            ->defaultSort('-date')
            ->allowedSorts(['reference', 'customer_name', 'date', 'pay_status', 'total_sales'])
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
                ->column('customer_name', __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column('date', __('Date'), canBeHidden: false, sortable: true, searchable: false, align: 'right')
                ->column('pay_status', __('Payment'), canBeHidden: false, sortable: true, searchable: false, type: 'icon')
                ->column('total_sales', __('Total Sales'), canBeHidden: false, sortable: true, type: 'number')
                ->defaultSort('-date');
        };
    }
}
