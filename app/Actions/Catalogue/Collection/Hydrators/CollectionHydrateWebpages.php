<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-15h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Collection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Collection;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class CollectionHydrateWebpages
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
            'number_parent_webpages' => $collection->webpageHasCollections()->count(),
        ];

        $collection->stats->update($stats);
    }

}
