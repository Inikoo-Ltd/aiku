<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 18 Jul 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Ordering\Order\CalculateOrderDiscounts;
use App\Models\Ordering\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Enumerable;

trait WithRecalculateOrdersInBasket
{
    protected function recalculateOrdersInBasket(Enumerable $orders): void
    {
        /** @var Order $order */
        foreach ($orders as $order) {
            if ($order->updated_by_customer_at && $order->updated_by_customer_at->isAfter(Carbon::now()->subHours(3))) {
                CalculateOrderDiscounts::dispatch($order, true);
            } else {
                $randomDelay = rand(30, 600);
                CalculateOrderDiscounts::dispatch($order, true)->delay($randomDelay)->onQueue('hydrators-slave-low-priority');
            }
        }
    }
}
