<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 19:08:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateOrderStatePicked implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public string $jobQueue = 'sales';

    public function getJobUniqueId(int $shopId): int
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
            'number_orders_state_picked'              => $shop->orders()->where('state', OrderStateEnum::PICKED)->count(),
            'orders_state_picked_amount'              => $shop->orders()->where('state', OrderStateEnum::PICKED)->sum('net_amount'),
            'orders_state_picked_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::PICKED)->sum('org_net_amount'),
            'orders_state_picked_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::PICKED)->sum('grp_net_amount'),


            'number_orders_picked_today' => $shop->orders()->whereDate('picked_at', Carbon::Today())->count(),

            'orders_picked_today_amount'              => $shop->orders()->whereDate('picked_at', Carbon::Today())->sum('net_amount'),
            'orders_picked_today_amount_org_currency' => $shop->orders()->whereDate('picked_at', Carbon::Today())->sum('org_net_amount'),
            'orders_picked_today_amount_grp_currency' => $shop->orders()->whereDate('picked_at', Carbon::Today())->sum('grp_net_amount'),

        ];

        $shop->orderHandlingStats()->update($stats);
    }


}
