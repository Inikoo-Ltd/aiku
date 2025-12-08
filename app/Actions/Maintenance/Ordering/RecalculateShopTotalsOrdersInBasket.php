<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:30:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;

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
        foreach ($shop->orders()->where('state', OrderStateEnum::CREATING)->get() as $order) {
            CalculateOrderTotalAmounts::run($order, true, true, false, true);
        }
    }

}
