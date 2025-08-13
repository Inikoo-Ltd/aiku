<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Apr 2024 02:07:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Masters\MasterCollection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterCollectionHydrateMasterProducts implements ShouldBeUnique
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
            'number_products' => $masterCollection->products()->where('type', 'direct')->count(),
        ];

        $collectionStats = $masterCollection->stats;
        $collectionStats->update($stats);
    }

}
