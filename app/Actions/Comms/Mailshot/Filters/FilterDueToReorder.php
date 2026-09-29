<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\Filters;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Repeat customers whose estimated next order (last invoice plus their average time between
 * orders) falls in the coming week, or has passed by less than one of their usual intervals.
 * Later than that they are slipping away rather than due. Customers with an order already on its
 * way through the warehouse have reordered and are left out.
 */
class FilterDueToReorder
{
    public const int DAYS_AHEAD = 7;

    public function apply(Builder $query, array $filters): Builder
    {
        $dueFilter = Arr::get($filters, 'due_to_reorder');
        $isActive  = is_array($dueFilter) ? ($dueFilter['value'] ?? false) : $dueFilter;

        if (!$isActive) {
            return $query;
        }

        return $query
            ->whereExists(function (Builder $query) {
                $query->select(DB::raw(1))
                    ->from('customer_stats')
                    ->whereColumn('customer_stats.customer_id', 'customers.id')
                    ->where('customer_stats.expected_date_of_next_order', '<=', now()->addDays(self::DAYS_AHEAD))
                    ->whereRaw("customer_stats.expected_date_of_next_order >= now() - customer_stats.average_time_between_orders * interval '1 day'");
            })
            ->whereNotExists(function (Builder $query) {
                $query->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.customer_id', 'customers.id')
                    ->whereNull('orders.deleted_at')
                    ->whereIn('orders.state', [
                        OrderStateEnum::SUBMITTED->value,
                        OrderStateEnum::IN_WAREHOUSE->value,
                        OrderStateEnum::HANDLING->value,
                        OrderStateEnum::HANDLING_BLOCKED->value,
                        OrderStateEnum::PICKED->value,
                        OrderStateEnum::PACKING->value,
                        OrderStateEnum::PACKED->value,
                    ]);
            });
    }
}
