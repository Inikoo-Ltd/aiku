<?php
/*
 * author Arya Permana - Kirin
 * created on 26-06-2025-10h-48m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Collection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Collection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CollectionHydrateCollections implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Collection $collection): string
    {
        return $collection->id;
    }

    public function handle(Collection $collection): void
    {

        $stats         = [
            'number_collections'    => $collection->collections()->count(),
        ];

        $collectionStats = $collection->stats;

        $collectionStats->update($stats);
    }

}
