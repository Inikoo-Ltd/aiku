<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Apr 2024 13:42:37 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Accounting\Invoice\Transaction\UI;

use App\Actions\Accounting\InvoiceTransaction\UI\IndexInvoiceTransactions;
use App\Actions\RetinaAction;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaInvoiceTransactions extends RetinaAction
{
    protected Invoice $invoice;

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

        $queryBuilder = QueryBuilder::for(InvoiceTransaction::class);
        $queryBuilder->leftJoin('historic_assets', 'invoice_transactions.historic_asset_id', 'historic_assets.id');
        $queryBuilder->leftJoin('assets', 'invoice_transactions.asset_id', 'assets.id');

        $queryBuilder->select(
            [
                'invoice_transactions.invoice_id',
                'invoice_transactions.model_type',
                'invoice_transactions.in_process',
                'historic_assets.code',
                'historic_assets.name',
                'invoice_transactions.historic_asset_id',
                'assets.id',
                'assets.shop_id',
                DB::raw('SUM(invoice_transactions.quantity) as quantity'),
                DB::raw('SUM(invoice_transactions.net_amount) as net_amount'),
            ]
        );

        $queryBuilder->where('invoice_transactions.invoice_id', $invoice->id)
            ->addSelect(
                DB::raw("'{$invoice->currency->code}' AS currency_code"),
            )
            ->groupBy(
                'historic_assets.code',
                'invoice_transactions.invoice_id',
                'historic_assets.name',
                'assets.id',
                'invoice_transactions.in_process',
                'invoice_transactions.historic_asset_id',
                'assets.shop_id',
                'invoice_transactions.model_type'
            );


        $queryBuilder->defaultSort('code');

        return $queryBuilder->allowedSorts(['code', 'name', 'quantity', 'net_amount'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Invoice $invoice, $prefix = null): Closure
    {
        return IndexInvoiceTransactions::make()->tableStructure($invoice, $prefix);
    }

}
