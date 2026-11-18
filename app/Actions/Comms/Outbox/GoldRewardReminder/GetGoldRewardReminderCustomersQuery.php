<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\GoldRewardReminder;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The customers a Gold reward reminder goes to today: last invoiced exactly `daysAfter` days
 * ago, not ordered since, and still subscribed to the reminder. The email and the WhatsApp
 * outbox of the same code pick from it, so both reach the same customers.
 */
class GetGoldRewardReminderCustomersQuery
{
    use AsObject;

    public const GOLD_REWARD_WINDOW_DAYS = 30;

    public function handle(int $shopId, int $daysAfter, bool $withEmail = true): Builder
    {
        $compareDate = Carbon::now()->utc()->subDays($daysAfter)->endOfDay();

        $query = DB::table('customers');
        $query->leftJoin('customer_comms', 'customers.id', '=', 'customer_comms.customer_id');
        $query->where('customer_comms.is_subscribed_to_gold_reward_reminder', true);
        $query->where('customers.shop_id', $shopId);
        $query->whereDate('customers.last_invoiced_at', '=', $compareDate->toDateString());

        if ($withEmail) {
            $query->whereNotNull('customers.email');
            $query->where('customers.email', '!=', '');
        }

        $query->whereNull('customers.deleted_at');
        $query->whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('orders')
                ->whereColumn('orders.customer_id', 'customers.id')
                ->whereColumn('orders.submitted_at', '>', 'customers.last_invoiced_at')
                ->whereNotIn('orders.state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
                ->whereNull('orders.deleted_at');
        });
        $query->select('customers.id', 'customers.email');
        $query->orderBy('customers.shop_id');
        $query->orderBy('customers.id');

        return $query;
    }
}
