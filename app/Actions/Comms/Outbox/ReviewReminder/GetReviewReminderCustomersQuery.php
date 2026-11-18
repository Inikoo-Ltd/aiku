<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\ReviewReminder;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Customers subscribed to review reminders with an order dispatched exactly `daysAfter` days
 * ago and not reviewed yet, one row per customer with those orders. The email and the
 * WhatsApp outbox of the code pick from it, so both ask the same customers about the same orders.
 */
class GetReviewReminderCustomersQuery
{
    use AsObject;

    public function handle(int $shopId, int $daysAfter, ?string $dispatchedAfter = null): Builder
    {
        $compareDate = Carbon::now()->utc()->subDays($daysAfter)->endOfDay();

        $query = DB::table('customers');
        $query->where('customers.shop_id', $shopId);
        $query->join('customer_comms', 'customers.id', '=', 'customer_comms.customer_id');
        $query->where('customer_comms.is_subscribed_to_review_reminder', true);
        $query->rightJoin('orders', function ($join) use ($compareDate, $dispatchedAfter) {
            $join->on('customers.id', '=', 'orders.customer_id')
                ->where('orders.state', OrderStateEnum::DISPATCHED->value)
                ->whereNull('orders.deleted_at')
                ->whereDate('orders.dispatched_at', '=', $compareDate->toDateString());
            if ($dispatchedAfter) {
                $join->where('orders.dispatched_at', '>', $dispatchedAfter);
            }
        });
        $query->whereNotExists(function ($query) {
            $query->select('id')
                ->from('reviews')
                ->whereColumn('reviews.order_id', 'orders.id');
        });
        $query->select(
            'customers.id',
            'customers.email',
            DB::raw('STRING_AGG(orders.id::TEXT, \',\' ORDER BY orders.id) AS order_ids')
        );
        $query->groupBy('customers.id');
        $query->orderBy('customers.id');

        return $query;
    }
}
