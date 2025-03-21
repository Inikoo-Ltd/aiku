<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:53:53 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateAssets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {

        $stats         = [
            'number_assets' => $shop->assets()->where('is_main', true)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'assets',
                field: 'state',
                enum: AssetStateEnum::class,
                models: Asset::class,
                where: function ($q) use ($shop) {
                    $q->where('is_main', true)->where('shop_id', $shop->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'assets',
                field: 'type',
                enum: AssetTypeEnum::class,
                models: Asset::class,
                where: function ($q) use ($shop) {
                    $q->where('is_main', true)->where('shop_id', $shop->id);
                }
            )
        );


        $shop->stats()->update($stats);
    }

}
