<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jun 2026 15:39:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class RecalculateShopOrderDiscountsInBasket implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $shopId): string
    {
        return $shopId;
    }

    public function handle(int $shopId): void
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return;
        }
        /** @var Order $order */
        foreach ($shop->orders()->where('state', OrderStateEnum::CREATING)->get() as $order) {

            if ($order->updated_by_customer_at && $order->updated_by_customer_at->isAfter(Carbon::now()->subHours(3))) {
                CalculateOrderDiscounts::dispatch($order);
            } else {
                $randomDelay = rand(300, 7200);
                CalculateOrderDiscounts::dispatch($order)->delay($randomDelay)->onQueue('hydrators-slave-low-priority');
            }
        }
    }

}
