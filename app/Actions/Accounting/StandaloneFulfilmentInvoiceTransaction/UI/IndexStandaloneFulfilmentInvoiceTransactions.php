<?php

/*
 * author Arya Permana - Kirin
 * created on 27-02-2025-10h-51m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStandaloneFulfilmentInvoiceTransactions extends OrgAction
{
    protected Invoice $parent;

    public function handle(Invoice $parent, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('invoice_transactions.invoice_id', $parent->id);
        $queryBuilder->where('invoice_transactions.in_process', true);

        $queryBuilder->leftJoin('historic_assets', 'invoice_transactions.historic_asset_id', 'historic_assets.id');
        $queryBuilder->leftJoin('assets', 'invoice_transactions.asset_id', 'assets.id');

        $queryBuilder->select(
            [
                'invoice_transactions.id',
                'invoice_transactions.in_process',
                'invoice_transactions.invoice_id',
                'invoice_transactions.asset_id',
                'invoice_transactions.net_amount',
                'invoice_transactions.gross_amount',
                'invoice_transactions.model_type',
                'invoice_transactions.model_id',
                'invoice_transactions.quantity',
                'invoice_transactions.data',

                'historic_assets.id as historic_asset_id',
                'historic_assets.code as historic_asset_code',
                'historic_assets.name as historic_asset_name',
                'historic_assets.price as historic_asset_price',
                'historic_assets.unit as historic_asset_unit',
                'historic_assets.units as historic_asset_units',

                'assets.id as asset_id',
                'assets.type as asset_type',
                'assets.slug as asset_slug',
            ]
        );
        $queryBuilder->addSelect(
            DB::raw("'{$parent->currency->code}' AS currency_code")
        );

        $queryBuilder->defaultSort('id');

        return $queryBuilder->allowedSorts(['id', 'historic_asset_code'])
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

            $table->column(key: 'historic_asset_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'historic_asset_name', label: __('Description'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'historic_asset_price', label: __('Base price'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity', label: __('Quantity'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->column(key: 'net_amount', label: __('Net'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->column(key: 'actions', label: __('Action'), canBeHidden: false, sortable: true, searchable: true);
            $table->defaultSort('id');
        };
    }
}
