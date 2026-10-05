<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 30 Sep 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\Filters;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FilterLapsedCustomers
{
    /**
     * Customers who placed an order before the start of the range but none within it.
     * A range without an end date means "no order since the start date".
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $filter = Arr::get($filters, 'lapsed_customers');

        if (!is_array($filter) || !is_array($filter['value'] ?? null)) {
            return $query;
        }

        $dateRange = $filter['value']['date_range'] ?? null;
        $startDate = is_array($dateRange) ? ($dateRange[0] ?? null) : null;
        $endDate   = is_array($dateRange) ? ($dateRange[1] ?? null) : null;

        if (!$startDate) {
            return $query;
        }

        $placedOrders = fn (Builder $subQuery) => $subQuery->select(DB::raw(1))
            ->from('orders')
            ->whereColumn('orders.customer_id', 'customers.id')
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value]);

        $query->whereExists(function (Builder $subQuery) use ($placedOrders, $startDate) {
            $placedOrders($subQuery)->whereDate('orders.date', '<', $startDate);
        });

        $query->whereNotExists(function (Builder $subQuery) use ($placedOrders, $startDate, $endDate) {
            $placedOrders($subQuery)->whereDate('orders.date', '>=', $startDate);

            if ($endDate) {
                $subQuery->whereDate('orders.date', '<=', $endDate);
            }
        });

        return $query;
    }
}
