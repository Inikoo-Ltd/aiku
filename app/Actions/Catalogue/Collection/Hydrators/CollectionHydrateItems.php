<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Apr 2024 02:07:50 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Collection;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class CollectionHydrateItems
{
    use AsAction;
    use WithEnumStats;
    private Collection $collection;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->collection->id))->dontRelease()];
    }
    public function handle(Collection $collection): void
    {

        $stats         = [
            'number_families'    => $collection->families()->count(),
            'number_products'    => $collection->products()->count(),
        ];

        $collection->stats->update($stats);
    }

}
