<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:30:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;

class RecalculateShopTotalsOrdersInBasket implements ShouldBeUnique
{
    use WithActionUpdate;
    use WithFixedAddressActions;

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
            if ($order->updated_by_customer_at && $order->updated_by_customer_at->isAfter(Carbon::now()->subDay())) {
                CalculateOrderTotalAmounts::dispatch($order, true, true, false, true);
            } else {
                $randomDelay = rand(300, 7200);
                CalculateOrderTotalAmounts::dispatch($order, true, true, false, false)->delay($randomDelay)->onQueue('hydrators-slave');
            }
        }
    }

}
