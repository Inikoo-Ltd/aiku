<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Transaction\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Services\QueryBuilder;
use Illuminate\Support\Facades\DB;

class IndexNonProductItems extends OrgAction
{
    public function handle(Order $order, $prefix = null): \Illuminate\Database\Eloquent\Collection
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Transaction::class);
        $query->where('transactions.order_id', $order->id);
        $query->leftjoin('orders', 'transactions.order_id', '=', 'orders.id');
        $query->leftjoin('currencies', 'orders.currency_id', '=', 'currencies.id');

        $query->whereNotIn('transactions.model_type', ['Product', 'Service']);

        $query->leftJoin('charges', function ($join) {
            $join->on('transactions.model_id', '=', 'charges.id')
                ->where('transactions.model_type', '=', 'Charge');
        });

        $query->leftJoin('adjustments', function ($join) {
            $join->on('transactions.model_id', '=', 'adjustments.id')
                ->where('transactions.model_type', '=', 'Adjustment');
        });

        $query->leftJoin('shipping_zones', function ($join) {
            $join->on('transactions.model_id', '=', 'shipping_zones.id')
                ->where('transactions.model_type', '=', 'ShippingZone');
        });

        $query->defaultSort('transactions.id')
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
                'transactions.model_type',
                'currencies.code as currency_code',
                'orders.id as order_id',
                DB::raw(
                    "CASE 
                WHEN transactions.model_type = 'Charge' THEN charges.name
                WHEN transactions.model_type = 'Adjustment' THEN adjustments.type
                WHEN transactions.model_type = 'ShippingZone' THEN shipping_zones.name
                ELSE null
            END as asset_name"
                ),
            ]);

        return $query->get();
    }
}
