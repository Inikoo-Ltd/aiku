<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 07:05:41 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateCollections implements ShouldBeUnique
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
            'number_collections' => $shop->collections()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'collections',
                field: 'state',
                enum: CollectionStateEnum::class,
                models: Collection::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );

        $stats['number_current_collections'] = $stats['number_collections_state_active'] + $stats['number_collections_state_discontinuing'];


        $shop->stats()->update($stats);
    }


}
