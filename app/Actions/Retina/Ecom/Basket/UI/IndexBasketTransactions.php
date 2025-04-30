<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Basket\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexBasketTransactions extends OrgAction
{
    public function handle(Order $order, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('assets.code', $value)
                    ->orWhereStartWith('assets.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Transaction::class);

        $query->where('transactions.order_id', $order->id);

        $query->whereIn('transactions.model_type', ['Product', 'Service']);

        $query->leftjoin('assets', 'transactions.asset_id', '=', 'assets.id');
        $query->leftjoin('products', 'assets.model_id', '=', 'products.id');
        $query->leftjoin('orders', 'transactions.order_id', '=', 'orders.id');
        $query->leftjoin('currencies', 'orders.currency_id', '=', 'currencies.id');

        return $query->defaultSort('transactions.id')
            ->select([
                'transactions.id',
                'transactions.state',
                'transactions.status',
                'transactions.quantity_ordered',
                'transactions.quantity_bonus',
                'transactions.quantity_dispatched',
                'transactions.quantity_fail',
                'transactions.quantity_cancelled',
                'transactions.gross_amount',
                'transactions.net_amount',
                'transactions.created_at',
                'assets.code as asset_code',
                'assets.name as asset_name',
                'assets.type as asset_type',
                'products.id as product_id',
                'products.slug as product_slug',
                'currencies.code as currency_code',
                'orders.id as order_id',
            ])
            ->allowedSorts(['asset_code', 'asset_name', 'net_amount', 'quantity_ordered'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation|Shop|Customer|Order|Invoice|Asset $parent, $tableRows = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix, $tableRows) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->withFooterRows($tableRows);
            $table
                ->withEmptyState(
                    [
                        'title' => __("No transactions found"),
                    ]
                );

            $table->column(key: 'asset_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'asset_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_ordered', label: __('Quantity'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->column(key: 'net_amount', label: __('Net'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
            if ($parent instanceof Order) {
                $table->column(key: 'actions', label: __('action'), canBeHidden: false, sortable: true, searchable: true);
            }
        };
    }






}
