<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\BackInStockNotification;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Customers waiting for a product that came back in stock in the last week, one row per
 * customer. Each channel passes its own notified column so a reminder the email already
 * covered can still reach the same customer on WhatsApp, and the other way round.
 */
class GetBackInStockCustomersQuery
{
    use AsObject;

    public const EMAIL = 'email_notified_at';

    public const WHATSAPP = 'whatsapp_notified_at';

    public function handle(int $shopId, string $notifiedColumn): Builder
    {
        $now = Carbon::now()->utc();

        $baseQuery = DB::table('customers');
        $baseQuery->join('back_in_stock_reminders', 'customers.id', '=', 'back_in_stock_reminders.customer_id');
        $baseQuery->join('products', 'back_in_stock_reminders.product_id', '=', 'products.id');
        $baseQuery->select(
            'customers.id',
            'customers.email',
            'customers.shop_id',
            DB::raw('STRING_AGG(back_in_stock_reminders.id::TEXT, \',\' ORDER BY back_in_stock_reminders.id) AS reminder_ids'),
            DB::raw('STRING_AGG(back_in_stock_reminders.product_id::TEXT, \',\' ORDER BY back_in_stock_reminders.product_id) AS product_ids')
        );

        $baseQuery->where('back_in_stock_reminders.shop_id', $shopId);
        $baseQuery->whereNull('back_in_stock_reminders.'.$notifiedColumn);
        $baseQuery->where('products.available_quantity', '>', 0);
        $baseQuery->where('products.back_in_stock_since', '>', DB::raw('back_in_stock_reminders.created_at'));
        $baseQuery->where('products.back_in_stock_since', '>=', $now->copy()->subDays(7));

        $baseQuery->whereNull('customers.deleted_at');
        $baseQuery->whereNull('products.deleted_at');
        $baseQuery->orderBy('customers.id');
        $baseQuery->groupBy('customers.id');

        return $baseQuery;
    }
}
