<?php

/*
 * author Arya Permana - Kirin
 * created on 26-06-2025-10h-48m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterCollection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Masters\MasterCollection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterCollectionHydrateMasterCollections implements ShouldBeUnique
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
            'number_collections'    => $masterCollection->masterCollections()->count(),
        ];

        $collectionStats = $masterCollection->stats;

        $collectionStats->update($stats);
    }

}
