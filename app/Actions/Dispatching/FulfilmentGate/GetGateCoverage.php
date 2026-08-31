<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 30 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\FulfilmentGate;

use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetGateCoverage
{
    use AsObject;

    /**
     * @return array<int, array{ready_lines: int, total_lines: int}>
     */
    public function handle(array $orderIds): array
    {
        if (!$orderIds) {
            return [];
        }

        // ponytail: naive per-order coverage against current availability, no cross-order
        // allocation; move to FIFO allocation if queue-jumping becomes a real problem
        return DB::table('transactions')
            ->join('product_has_org_stocks', 'product_has_org_stocks.product_id', 'transactions.model_id')
            ->join('org_stocks', 'org_stocks.id', 'product_has_org_stocks.org_stock_id')
            ->where('transactions.model_type', 'Product')
            ->whereNull('transactions.deleted_at')
            ->whereIn('transactions.order_id', $orderIds)
            ->groupBy('transactions.order_id')
            ->select(
                'transactions.order_id',
                DB::raw('count(distinct transactions.id) filter (where org_stocks.quantity_available >= coalesce(product_has_org_stocks.quantity, 1) * (transactions.quantity_ordered + transactions.quantity_bonus)) as ready_lines'),
                DB::raw('count(distinct transactions.id) as total_lines')
            )
            ->get()
            ->keyBy('order_id')
            ->map(fn ($row) => ['ready_lines' => (int) $row->ready_lines, 'total_lines' => (int) $row->total_lines])
            ->all();
    }

    public function isFullyCoverable(Order $order): bool
    {
        $coverage = $this->handle([$order->id])[$order->id] ?? null;

        return $coverage !== null && $coverage['total_lines'] > 0 && $coverage['ready_lines'] >= $coverage['total_lines'];
    }
}
