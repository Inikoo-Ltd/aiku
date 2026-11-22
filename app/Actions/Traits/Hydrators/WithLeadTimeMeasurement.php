<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait WithLeadTimeMeasurement
{
    /**
     * @return array{measured_lead_time_days: int|null, lead_time_samples: int}
     */
    protected function measureLeadTime(string $column, int $id): array
    {
        return $this->measureLeadTimeOfOrders(fn (Builder $query) => $query->where('purchase_order_transactions.'.$column, $id));
    }

    /**
     * Days from submitting a purchase order to its goods being received, the median over the orders of
     * the last 12 months. A supplier ordered from once a year has fewer than 3 of those, so its
     * last 3 orders of any age are used instead of falling back to the hand-typed estimate. Each order
     * counts once, landing with its first delivery, however many deliveries or lines it was split into.
     * The median, so one order left open for two years (Aromatika00001-ES: 783 days) does not set it;
     * received_at, not booked_in_at, because a delivery can be booked in months after it arrived; and
     * orders received no later than they were submitted are history typed in after the fact, not lead times.
     *
     * @param  callable(Builder): mixed  $scope
     *
     * @return array{measured_lead_time_days: int|null, lead_time_samples: int}
     */
    protected function measureLeadTimeOfOrders(callable $scope): array
    {
        $receivedAt = 'min(coalesce(stock_deliveries.received_at, stock_deliveries.booked_in_at, stock_deliveries.placed_at))';

        $days = DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->join('purchase_order_stock_delivery', 'purchase_order_stock_delivery.purchase_order_id', 'purchase_orders.id')
            ->join('stock_deliveries', 'stock_deliveries.id', 'purchase_order_stock_delivery.stock_delivery_id')
            ->tap($scope)
            ->whereNull('purchase_orders.deleted_at')
            ->whereNotNull('purchase_orders.submitted_at')
            ->whereRaw('coalesce(stock_deliveries.booked_in_at, stock_deliveries.placed_at) is not null')
            ->groupBy('purchase_orders.id', 'purchase_orders.submitted_at')
            ->havingRaw($receivedAt.' > purchase_orders.submitted_at')
            ->orderByDesc('purchase_orders.submitted_at')
            ->orderByDesc('purchase_orders.id')
            ->selectRaw('extract(epoch from '.$receivedAt.' - purchase_orders.submitted_at) / 86400 as days')
            ->selectRaw("purchase_orders.submitted_at >= now() - interval '12 months' as is_recent")
            ->get()
            ->filter(fn (object $row, int $index) => $row->is_recent || $index < 3)
            ->pluck('days')
            ->map(fn ($days) => (float) $days);

        $samples = $days->count();

        return [
            'measured_lead_time_days' => $samples >= 3 ? max(1, (int) round($days->median())) : null,
            'lead_time_samples'       => $samples,
        ];
    }
}
