<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Oct 2025 12:25:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Masters\MasterCollection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterCollectionHydrateParents implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterCollection $masterCollection): string
    {
        return $masterCollection->id;
    }

    public function handle(MasterCollection $masterCollection): void
    {

        $stats         = [
            'number_parents'    => $masterCollection->parentMasterDepartments()->count() + $masterCollection->parentMasterSubDepartments()->count(),
        ];


        $collectionStats = $masterCollection->stats;

        $collectionStats->update($stats);


    }

}
