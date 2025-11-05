<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 23:18:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateOrderStateCreating implements ShouldBeUnique
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
            'number_orders_state_creating'              => $shop->orders()->where('state', OrderStateEnum::CREATING)->count(),
            'orders_state_creating_amount'              => $shop->orders()->where('state', OrderStateEnum::CREATING)->sum('net_amount'),
            'orders_state_creating_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::CREATING)->sum('org_net_amount'),
            'orders_state_creating_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::CREATING)->sum('grp_net_amount'),

        ];

        $shop->orderHandlingStats()->update($stats);
    }


}
