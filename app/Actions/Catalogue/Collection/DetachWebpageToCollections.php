<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 10-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateWebpages;
use App\Actions\OrgAction;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateCollections;
use App\Models\Catalogue\Collection;
use App\Models\Web\Webpage;

class DetachWebpageToCollections extends OrgAction
{
    public function handle(Collection $collection, Webpage $webpage): Collection
    {
        $collection->collectionHasWebpages()
            ->where('webpage_id', $webpage->id)
            ->delete();

        $collection->refresh();
        $webpage->refresh();

        CollectionHydrateWebpages::dispatch($collection);
        WebpageHydrateCollections::dispatch($webpage);

        return $collection;
    }

    public function action(Collection $collection, Webpage $webpage): Collection
    {
        $this->asAction = true;
        $this->initialisationFromShop($collection->shop, []);

        return $this->handle($collection, $webpage);
    }

    public function asController(Collection $collection, Webpage $webpage): void
    {
        $this->initialisationFromShop($collection->shop, []);

        $this->handle($collection, $webpage);
    }
}
