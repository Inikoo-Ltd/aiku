<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Jun 2025 12:48:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Collection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CollectionHydrateParents implements ShouldBeUnique
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
            'number_parents'    => $collection->departments()->count() + $collection->subDepartments()->count(),
        ];


        $collectionStats = $collection->stats;

        $collectionStats->update($stats);


    }

}
