<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Jan 2025 14:17:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction\UI;

use App\Actions\OrgAction;
use App\Enums\UI\Accounting\RefundInProcessTabsEnum;
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

        $queryBuilder->leftJoin('historic_assets', 'invoice_transactions.historic_asset_id', 'historic_assets.id')
            ->leftJoin('assets', 'invoice_transactions.asset_id', 'assets.id');

        $commonSelect = [
            'historic_assets.code',
            'historic_assets.name',
            'assets.slug',
            'assets.price',
            DB::raw($refund->id.' as refund_id'),
            DB::raw("'{$invoice->currency->code}' AS currency_code")
        ];

        if (RefundInProcessTabsEnum::ITEMS_IN_PROCESS->value === $prefix) {
            $queryBuilder->select(array_merge([
            'invoice_transactions.id',
            'invoice_transactions.updated_at',
            'invoice_transactions.in_process',
            'quantity',
            'net_amount',
            ], $commonSelect))->where('invoice_transactions.invoice_id', $invoice->id);
        } else {
            $queryBuilder->leftJoin('invoice_transactions as original_invoice_transaction', 'invoice_transactions.invoice_transaction_id', 'original_invoice_transaction.id')
            ->select(array_merge([
                'original_invoice_transaction.id',
                'original_invoice_transaction.updated_at',
                'original_invoice_transaction.in_process',
                'original_invoice_transaction.quantity',
                'original_invoice_transaction.net_amount',
            ], $commonSelect))->where('invoice_transactions.invoice_id', $refund->id);
        }


        $queryBuilder->defaultSort('code');

        return $queryBuilder->allowedSorts(['code', 'name', 'quantity', 'net_amount', 'updated_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Invoice $invoice, $prefix = null): Closure
    {
        return IndexInvoiceTransactionsGroupedByAsset::make()->tableStructure($invoice, $prefix);
    }


}
