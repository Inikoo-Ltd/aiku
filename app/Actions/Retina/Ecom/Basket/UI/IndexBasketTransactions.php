<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Basket\UI;

use App\Actions\Ordering\Transaction\UI\IndexTransactions;
use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Ordering\Order;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexBasketTransactions extends OrgAction
{
    public function handle(Order $order, $prefix = null): LengthAwarePaginator
    {
        return IndexTransactions::run($order, $prefix);
    }

    public function tableStructure($tableRows = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $tableRows) {
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

            $table->column(key: 'image', label: '', canBeHidden: false, sortable: false, searchable: false);
            $table->column(key: 'asset_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'asset_name', label: __('Product Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'price', label: __('Price'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
            $table->column(key: 'quantity_ordered', label: __('Quantity'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->column(key: 'net_amount', label: __('Net'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
            $table->column(key: 'actions', label: __('action'), canBeHidden: false, sortable: false, searchable: false);

        };
    }






}
