<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-10h-02m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Purge\PurgeStateEnum;
use App\Enums\Ordering\Purge\PurgeTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Purge;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePurges implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $stats = [
            'number_purges' => $shop->purges()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'purges',
                field: 'state',
                enum: PurgeStateEnum::class,
                models: Purge::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'purges',
                field: 'type',
                enum: PurgeTypeEnum::class,
                models: Purge::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );

        $shop->orderingStats()->update($stats);
    }

}
