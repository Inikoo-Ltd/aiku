<?php

namespace App\Actions\Dropshipping\Invoices\UI;

use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexInvoices extends OrgAction
{
    public function handle(Shop $shop, Platform $platform, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('invoices.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Invoice::class);
        $query->where('invoices.in_process', false);
        $query->where('invoices.shop_id', $shop->id);
        $query->where('invoices.platform_id', $platform->id);
        $query->where('invoices.type', InvoiceTypeEnum::INVOICE);
        $query->leftJoin('currencies', 'invoices.currency_id', 'currencies.id');

        return $query
            ->select([
                'invoices.reference',
                'invoices.customer_name',
                'invoices.date',
                'invoices.pay_status',
                'invoices.net_amount',
                'invoices.total_amount',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
            ])
            ->defaultSort('-date')
            ->allowedSorts(['number', 'pay_status', 'total_amount', 'net_amount', 'date', 'customer_name', 'reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'pay_status', label: __('Payment'), canBeHidden: false, sortable: true, searchable: true, type: 'icon')
                ->column(key: 'net_amount', label: __('Net'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->column(key: 'total_amount', label: __('Total'), canBeHidden: false, sortable: true, searchable: true, type: 'number')->defaultSort('-date');
        };
    }
}
