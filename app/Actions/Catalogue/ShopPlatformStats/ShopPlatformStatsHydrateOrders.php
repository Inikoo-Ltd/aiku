<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:53:53 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ShopPlatformStats;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopPlatformStatsHydrateOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop, Platform $platform): void
    {
        $stats = [
            'number_orders'    => $shop->orders()->where('platform_id', $platform->id)->count(),

        ];


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'orders',
                field: 'state',
                enum: OrderStateEnum::class,
                models: Order::class,
                where: function ($q) use ($shop, $platform) {
                    $q->where('shop_id', $shop->id)
                        ->where('platform_id', $platform->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'orders',
                field: 'handing_type',
                enum: OrderHandingTypeEnum::class,
                models: Order::class,
                where: function ($q) use ($shop, $platform) {
                    $q->where('shop_id', $shop->id)
                        ->where('platform_id', $platform->id);
                }
            )
        );

        $shop->platformStats()->where('platform_id', $platform->id)->update($stats);
    }
}
