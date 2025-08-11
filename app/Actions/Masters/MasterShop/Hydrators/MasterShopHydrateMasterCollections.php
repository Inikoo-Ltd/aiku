<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\MasterCollection\MasterCollectionStateEnum;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateMasterCollections implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterShop $masterShop): string
    {
        return $masterShop->id;
    }

    public function handle(MasterShop $masterShop): void
    {
        $stats = [
            'number_master_collections' => $masterShop->masterCollections()->count(),
        ];

        $count = $masterShop->masterCollections()->selectRaw("master_collections.state, count(*) as total")
            ->groupBy('master_collections.state')
            ->pluck('total', 'master_collections.state')->all();

        foreach (MasterCollectionStateEnum::cases() as $case) {
            $stats["number_master_collections_state_".$case->snake()] = Arr::get($count, $case->value, 0);
        }

        $stats['number_current_master_collections'] = $stats['number_master_collections_state_active'];

        $masterShop->stats()->update($stats);
    }
}