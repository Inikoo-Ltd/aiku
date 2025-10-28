<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Apr 2025 16:19:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Models\Catalogue\Collection;
use App\Models\Masters\MasterCollection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterCollectionHydrateCollections implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterCollection $masterCollection): string
    {
        return $masterCollection->id;
    }

    public function handle(MasterCollection $masterCollection): void
    {
        $stats = [
            'number_collections' => DB::table('collections')
                ->where('master_collection_id', $masterCollection->id)
                ->count(),
            'number_current_collections' => DB::table('collections')
                ->whereIn('state', [
                    CollectionStateEnum::ACTIVE
                ])
                ->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'collections',
                field: 'state',
                enum: CollectionStateEnum::class,
                models: Collection::class,
                where: function ($q) use ($masterCollection) {
                    $q->where('master_collection_id', $masterCollection->id);
                }
            )
        );

        $masterCollection->stats()->update($stats);
    }


}
