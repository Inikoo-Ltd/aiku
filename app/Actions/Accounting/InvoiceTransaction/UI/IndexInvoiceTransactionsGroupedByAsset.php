<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Apr 2024 13:42:37 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction\UI;

use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexInvoiceTransactionsGroupedByAsset extends OrgAction
{
    public function handle(Invoice $invoice, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('invoice_transactions.number', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        if ($invoice->deleted_at) {
            $baseQuery    = InvoiceTransaction::withTrashed();
            $queryBuilder = QueryBuilder::for($baseQuery);
        } else {
            $queryBuilder = QueryBuilder::for(InvoiceTransaction::class);
        }

        $queryBuilder->leftJoin('historic_assets', 'invoice_transactions.historic_asset_id', 'historic_assets.id');
        $queryBuilder->leftJoin('assets', 'invoice_transactions.asset_id', 'assets.id');

        $queryBuilder->select(
            [
                'invoice_transactions.invoice_id',
                'invoice_transactions.model_type',
                'invoice_transactions.in_process',
                'invoice_transactions.data',
                'historic_assets.code',
                'historic_assets.name',
                'invoice_transactions.historic_asset_id',
                'assets.id as asset_id',
                'assets.shop_id',
                DB::raw('SUM(invoice_transactions.quantity) as quantity'),
                DB::raw('SUM(invoice_transactions.net_amount) as net_amount'),
            ]
        );

        $queryBuilder->where('invoice_transactions.invoice_id', $invoice->id)
            ->addSelect(
                DB::raw("'{$invoice->currency->code}' AS currency_code"),
            )
            ->addSelect(
                DB::raw("count(*) as number_grouped_transactions"),
            )
            ->groupBy(
                'historic_assets.code',
                'invoice_transactions.invoice_id',
                'invoice_transactions.data',
                'historic_assets.name',
                'assets.id',
                'invoice_transactions.in_process',
                'invoice_transactions.historic_asset_id',
                'assets.shop_id',
                'invoice_transactions.model_type',
            );


        $queryBuilder->defaultSort('code');

        return $queryBuilder->allowedSorts(['code', 'name', 'quantity', 'net_amount'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Invoice $invoice, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $invoice) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }
            $table->withModelOperations()->withGlobalSearch();
            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'name', label: __('description'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity', label: __('quantity'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->column(key: 'net_amount', label: __('net'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            if ($invoice->type === InvoiceTypeEnum::REFUND && $invoice->in_process) {
                $table->column(key: 'action', label: __('action'), canBeHidden: false);
            }
            $table->defaultSort('code');
        };
    }

}
