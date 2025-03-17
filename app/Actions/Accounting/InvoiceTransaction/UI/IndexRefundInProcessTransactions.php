<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Jan 2025 14:17:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
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

class IndexRefundInProcessTransactions extends OrgAction
{
    public function handle(Invoice $refund, Invoice $invoice, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('invoice_transactions.number', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(InvoiceTransaction::class);
        $queryBuilder->leftJoin('historic_assets', 'invoice_transactions.historic_asset_id', 'historic_assets.id');
        $queryBuilder->leftJoin('assets', 'invoice_transactions.asset_id', 'assets.id');
        // $queryBuilder->leftJoin('invoice_transactions as invoice_transaction_refund', 'invoice_transactions.id', 'invoice_transaction_refund.invoice_transaction_id');



        $queryBuilder->select(
            [
                'invoice_transactions.id',
                'invoice_transactions.in_process',
                'historic_assets.code',
                'historic_assets.name',
                'assets.slug',
                'assets.price',
                DB::raw('SUM(invoice_transactions.quantity) as quantity'),
                DB::raw('SUM(invoice_transactions.net_amount) as net_amount'),
                DB::raw($refund->id.'  as refund_id'),

            ]
        );


        $queryBuilder->where('invoice_transactions.invoice_id', $invoice->id)
            ->addSelect(
                DB::raw("'{$invoice->currency->code}' AS currency_code")
            )
            ->groupBy(
                'invoice_transactions.id',
                'historic_assets.code',
                'historic_assets.name',
                'assets.price',
                'assets.slug',
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
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withModelOperations()
                ->withGlobalSearch();

            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'name', label: __('description'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity', label: __('quantity'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->column(key: 'net_amount', label: __('net'), canBeHidden: false, sortable: true, searchable: true, type: 'number');

            if ($invoice instanceof Invoice && $invoice->type === InvoiceTypeEnum::REFUND && $invoice->in_process) {
                $table->column(key: 'action', label: __('action'), canBeHidden: false);
            }

            $table->defaultSort('-invoice_transactions.updated_at');
        };
    }


}
