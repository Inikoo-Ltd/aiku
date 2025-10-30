<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 23:27:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateOrderStateInWarehouse implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public string $jobQueue = 'sales';

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

        $stats = [
            'number_orders_state_in_warehouse'              => $shop->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->count(),
            'orders_state_in_warehouse_amount'              => $shop->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('net_amount'),
            'orders_state_in_warehouse_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('org_net_amount'),
            'orders_state_in_warehouse_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('grp_net_amount'),

        ];

        $shop->orderHandlingStats()->update($stats);
    }


}
