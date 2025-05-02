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
use App\Models\Ordering\Order;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexBasketTransactions extends OrgAction
{
    public function handle(Order $order, $prefix = null): LengthAwarePaginator
    {
        return IndexTransactions::run($order, $prefix);
    }

    public function tableStructure(Order $order, $tableRows = null, $prefix = null): Closure
    {
        return IndexTransactions::make()->tableStructure($order, $tableRows, $prefix);
    }






}
