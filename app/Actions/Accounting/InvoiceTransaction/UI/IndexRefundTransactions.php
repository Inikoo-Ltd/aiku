<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Jan 2025 14:17:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        $queryBuilder->where('invoice_id', $refund->id);
        $queryBuilder->leftJoin('historic_assets', 'invoice_transactions.historic_asset_id', 'historic_assets.id');
        $queryBuilder->leftJoin('assets', 'invoice_transactions.asset_id', 'assets.id');

        $queryBuilder->select(
            [
                'invoice_transactions.id',
                'invoice_transactions.in_process',
                'invoice_transactions.historic_asset_id',
                'historic_assets.code',
                'historic_assets.name',
                'assets.slug',
                'invoice_transactions.quantity',
                'invoice_transactions.net_amount',
                'invoice_transactions.gross_amount',

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
        return IndexInvoiceTransactionsGroupedByAsset::make()->tableStructure($invoice, $prefix);
    }



}
