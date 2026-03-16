<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 23:33:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateOrderStatePacking implements ShouldBeUnique
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
            'number_orders_state_packing'              => $shop->orders()->where('state', OrderStateEnum::PACKING)->count(),
            'orders_state_packing_amount'              => $shop->orders()->where('state', OrderStateEnum::PACKING)->sum('net_amount'),
            'orders_state_packing_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::PACKING)->sum('org_net_amount'),
            'orders_state_packing_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::PACKING)->sum('grp_net_amount'),


            'number_orders_packing_today' => $shop->orders()->whereDate('packing_at', Carbon::Today())->count(),

            'orders_packing_today_amount'              => $shop->orders()->whereDate('packing_at', Carbon::Today())->sum('net_amount'),
            'orders_packing_today_amount_org_currency' => $shop->orders()->whereDate('packing_at', Carbon::Today())->sum('org_net_amount'),
            'orders_packing_today_amount_grp_currency' => $shop->orders()->whereDate('packing_at', Carbon::Today())->sum('grp_net_amount'),

        ];

        $shop->orderHandlingStats()->update($stats);
    }


}
