<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Jun 2025 12:48:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Masters\MasterCollection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterCollectionHydrateFamilies implements ShouldBeUnique
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
            'number_families'    => $masterCollection->masterFamilies()->count(),
        ];

        $collectionStats = $masterCollection->stats;

        $collectionStats->update($stats);
    }

}
