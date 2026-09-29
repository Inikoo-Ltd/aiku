<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\SalesTarget\Concerns;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\DB;

trait HasOrdersPipeline
{
    public const array PIPELINE_STATES = [
        OrderStateEnum::SUBMITTED,
        OrderStateEnum::IN_WAREHOUSE,
        OrderStateEnum::HANDLING,
        OrderStateEnum::HANDLING_BLOCKED,
        OrderStateEnum::PICKED,
        OrderStateEnum::PACKING,
        OrderStateEnum::PACKED,
    ];

    /**
     * @return array{amount: float, orders: int, submitted_amount: float, in_warehouse_amount: float}
     */
    private function pipeline(Shop $shop): array
    {
        $rows = DB::table('orders')
            ->where('orders.shop_id', $shop->id)
            ->whereIn('orders.state', array_map(fn (OrderStateEnum $state) => $state->value, self::PIPELINE_STATES))
            ->whereNull('orders.deleted_at')
            ->whereNotExists(fn ($query) => $query->from('org_partners')->whereColumn('org_partners.customer_id', 'orders.customer_id'))
            ->selectRaw('orders.state = ? as is_submitted, count(*) as orders, coalesce(sum(orders.org_net_amount), 0) as amount', [OrderStateEnum::SUBMITTED->value])
            ->groupByRaw('1')
            ->get();

        $submitted   = (float) ($rows->firstWhere('is_submitted', true)->amount ?? 0);
        $inWarehouse = (float) ($rows->firstWhere('is_submitted', false)->amount ?? 0);

        return [
            'amount'              => round($submitted + $inWarehouse, 2),
            'orders'              => (int) $rows->sum('orders'),
            'submitted_amount'    => round($submitted, 2),
            'in_warehouse_amount' => round($inWarehouse, 2),
        ];
    }
}
