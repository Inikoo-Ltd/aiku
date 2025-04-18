<?php

/*
 * author Arya Permana - Kirin
 * created on 20-03-2025-15h-51m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceTransaction\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;

class IndexInvoiceTransactions extends OrgAction
{
    public function handle(Invoice $invoice, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(InvoiceTransaction::class);
        $queryBuilder->where('invoice_transactions.invoice_id', $invoice->id);
        $queryBuilder->leftJoin('historic_assets', 'invoice_transactions.historic_asset_id', 'historic_assets.id');
        $queryBuilder->leftJoin('assets', 'invoice_transactions.asset_id', 'assets.id');
        $queryBuilder->leftJoin('invoices', 'invoice_transactions.invoice_id', 'invoices.id');
        $queryBuilder->leftJoin('currencies', 'invoices.currency_id', 'currencies.id');
        $queryBuilder
            ->defaultSort('invoice_transactions.id')
            ->select([
                'invoice_transactions.model_type',
                'invoice_transactions.model_id',
                'invoice_transactions.in_process',
                'invoice_transactions.data',
                'historic_assets.code',
                'historic_assets.name as description',
                'invoice_transactions.historic_asset_id',
                'assets.id as asset_id',
                'assets.shop_id as asset_shop_id',
                'invoice_transactions.quantity',
                'invoice_transactions.net_amount',
                'invoice_transactions.id',
                'invoice_transactions.recurring_bill_transaction_id',
                'currencies.code as currency_code',
                'currencies.id as currency_id'
            ]);


        return $queryBuilder->allowedSorts(['id', 'code', 'description'])
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

            $table
                ->withModelOperations()
                ->withGlobalSearch();

            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'description', label: __('description'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity', label: __('quantity'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->column(key: 'net_amount', label: __('net'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->defaultSort('code');
        };
    }


}
