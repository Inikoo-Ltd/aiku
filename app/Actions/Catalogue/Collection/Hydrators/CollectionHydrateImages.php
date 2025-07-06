<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Jul 2025 19:25:21 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Actions\Traits\WithImageStats;
use App\Models\Catalogue\Collection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CollectionHydrateImages implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithImageStats;

    public function getJobUniqueId(Collection $collection): string
    {
        return $collection->id;
    }

    public function handle(Collection $collection): void
    {
        // Calculate image statistics using the trait method
        $stats = $this->calculateImageStatsUsingDB(
            model: $collection,
            modelType: 'Collection',
            hasPublicImages: true,
            useTotalImageSize: false
        );

        // Update collection stats
        $collection->stats->update($stats);
    }
}
