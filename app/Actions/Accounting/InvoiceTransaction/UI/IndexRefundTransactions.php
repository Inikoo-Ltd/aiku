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

class IndexRefundTransactions extends OrgAction
{
    public function handle(Invoice $refund, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('historic_assets.code', $value)
                    ->orWhereWith('historic_assets.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }




        $queryBuilder = QueryBuilder::for(InvoiceTransaction::class);
        $queryBuilder->where('invoice_transactions.invoice_id', $refund->id);
        $queryBuilder->leftJoin('historic_assets', 'invoice_transactions.historic_asset_id', 'historic_assets.id');
        $queryBuilder->leftJoin('assets', 'invoice_transactions.asset_id', 'assets.id');
        $queryBuilder->leftJoin('invoice_transactions as original_invoice_transaction', 'invoice_transactions.invoice_transaction_id', 'original_invoice_transaction.id');

        $queryBuilder->select(
            [
                'original_invoice_transaction.id',
                'original_invoice_transaction.updated_at',
                'original_invoice_transaction.in_process',
                'historic_assets.code',
                'historic_assets.name',
                'assets.slug',
                'assets.price',
                'original_invoice_transaction.quantity',
                'original_invoice_transaction.net_amount',
                DB::raw($refund->id.'  as refund_id'),

            ]
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
