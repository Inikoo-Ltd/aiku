<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 23:30:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateOrderStateFinalised implements ShouldBeUnique
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
            'number_orders_state_finalised'              => $shop->orders()->where('state', OrderStateEnum::FINALISED)->count(),
            'orders_state_finalised_amount'              => $shop->orders()->where('state', OrderStateEnum::FINALISED)->sum('net_amount'),
            'orders_state_finalised_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::FINALISED)->sum('org_net_amount'),
            'orders_state_finalised_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::FINALISED)->sum('grp_net_amount'),

            'number_orders_finalised_today'              => $shop->orders()->whereDate('finalised_at', Carbon::Today())->count(),
            'orders_finalised_today_amount'              => $shop->orders()->whereDate('finalised_at', Carbon::Today())->sum('net_amount'),
            'orders_finalised_today_amount_org_currency' => $shop->orders()->whereDate('finalised_at', Carbon::Today())->sum('org_net_amount'),
            'orders_finalised_today_amount_grp_currency' => $shop->orders()->whereDate('finalised_at', Carbon::Today())->sum('grp_net_amount'),

        ];

        $shop->orderHandlingStats()->update($stats);
    }


}
