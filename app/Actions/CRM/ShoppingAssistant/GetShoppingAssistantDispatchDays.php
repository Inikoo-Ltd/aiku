<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Days within which 9 out of 10 of a shop's orders were dispatched over the last 30 days, counted
 * in calendar time from the order being placed and rounded up; null when fewer than MIN_ORDERS
 * were dispatched, so a quiet shop makes no claim. Never less than 2, which keeps the customer
 * sentence in the plural in every language and errs on the slow side.
 *
 * ponytail: calendar days, weekends and bank holidays included, read once every six hours per
 * shop; switch to working days if customers find the figure too pessimistic.
 */
class GetShoppingAssistantDispatchDays
{
    use AsAction;

    public const int MIN_ORDERS = 30;

    public function handle(Shop $shop): ?int
    {
        return Cache::remember("shopping_assistant:dispatch_days:$shop->id", now()->addHours(6), function () use ($shop) {
            $row = DB::selectOne(
                <<<'SQL'
                select count(*) as orders,
                    percentile_cont(0.9) within group (order by extract(epoch from (dispatched_at - submitted_at))) as seconds
                from orders
                where shop_id = ?
                    and dispatched_at >= ?
                    and submitted_at is not null
                    and dispatched_at > submitted_at
                SQL,
                [$shop->id, now()->subDays(30)->toDateTimeString()]
            );

            return $row && $row->orders >= self::MIN_ORDERS ? max(2, (int) ceil($row->seconds / 86400)) : null;
        });
    }
}
