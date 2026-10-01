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

class FilterOrderedInPeriod
{
    /**
     * Customers with at least one placed order (not a basket, not cancelled) dated within the range.
     * A range without an end date is open-ended, so "since 1 Apr 2026" is a range with only a start,
     * and an empty range means the customer has placed an order at any time.
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $filter = Arr::get($filters, 'ordered_in_period');

        if (!is_array($filter) || !array_key_exists('value', $filter)) {
            return $query;
        }

        $dateRange = $filter['value']['date_range'] ?? null;
        $startDate = is_array($dateRange) ? ($dateRange[0] ?? null) : null;
        $endDate   = is_array($dateRange) ? ($dateRange[1] ?? null) : null;

        $query->whereExists(function (Builder $subQuery) use ($startDate, $endDate) {
            $subQuery->select(DB::raw(1))
                ->from('orders')
                ->whereColumn('orders.customer_id', 'customers.id')
                ->whereNull('orders.deleted_at')
                ->whereNotIn('orders.state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value]);

            if ($startDate) {
                $subQuery->whereDate('orders.date', '>=', $startDate);
            }

            if ($endDate) {
                $subQuery->whereDate('orders.date', '<=', $endDate);
            }
        });

        return $query;
    }
}
