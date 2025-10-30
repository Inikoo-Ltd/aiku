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

class ShopHydrateOrderStatePacked implements ShouldBeUnique
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
            'number_orders_state_packed'              => $shop->orders()->where('state', OrderStateEnum::PACKED)->count(),
            'orders_state_packed_amount'              => $shop->orders()->where('state', OrderStateEnum::PACKED)->sum('net_amount'),
            'orders_state_packed_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::PACKED)->sum('org_net_amount'),
            'orders_state_packed_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::PACKED)->sum('grp_net_amount'),


            'number_orders_packed_today' => $shop->orders()->whereDate('packed_at', Carbon::Today())->count(),

            'orders_packed_today_amount'              => $shop->orders()->whereDate('packed_at', Carbon::Today())->sum('net_amount'),
            'orders_packed_today_amount_org_currency' => $shop->orders()->whereDate('packed_at', Carbon::Today())->sum('org_net_amount'),
            'orders_packed_today_amount_grp_currency' => $shop->orders()->whereDate('packed_at', Carbon::Today())->sum('grp_net_amount'),

        ];

        $shop->orderHandlingStats()->update($stats);
    }


}
