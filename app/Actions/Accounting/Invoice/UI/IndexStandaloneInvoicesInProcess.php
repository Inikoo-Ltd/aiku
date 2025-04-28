<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Feb 2025 12:51:07 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStandaloneInvoicesInProcess extends OrgAction
{
    public function handle(FulfilmentCustomer $fulfilmentCustomer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('invoices.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(Invoice::class);
        $queryBuilder->where('invoices.in_process', true);
        $queryBuilder->where('invoices.type', InvoiceTypeEnum::INVOICE);


        $queryBuilder->where('invoices.customer_id', $fulfilmentCustomer->customer->id);

        $queryBuilder->leftjoin('organisations', 'invoices.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftjoin('shops', 'invoices.shop_id', '=', 'shops.id');

        $queryBuilder->defaultSort('-date')
            ->select([
                'invoices.reference',
                'invoices.total_amount',
                'invoices.net_amount',
                'invoices.pay_status',
                'invoices.date',
                'invoices.type',
                'invoices.created_at',
                'invoices.updated_at',
                'invoices.in_process',
                'invoices.slug',
                'invoices.original_invoice_id',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->leftJoin('currencies', 'invoices.currency_id', 'currencies.id')
            ->leftJoin('invoice_stats', 'invoices.id', 'invoice_stats.invoice_id');


        return $queryBuilder->allowedSorts(['number', 'pay_status', 'total_amount', 'net_amount', 'date', 'customer_name', 'reference'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table->withGlobalSearch();


            $table
                ->withGlobalSearch()
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);


            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');


            $table->betweenDates(['date']);
            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->defaultSort('-date');
        };
    }


}
