<?php
/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-08h-38m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateItems;
use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateWebpages;
use App\Actions\OrgAction;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateCollections;
use App\Models\Catalogue\Collection;
use App\Models\Web\Webpage;

class DetachCollectionFromWebpage extends OrgAction
{
    public function handle(Webpage $webpage, Collection $collection): Collection
    {
        $webpage->webpageHasCollections()
            ->where('collection_id', $collection->id)
            ->delete();

        $webpage->refresh();
        $collection->refresh();

        CollectionHydrateWebpages::dispatch($collection);
        WebpageHydrateCollections::dispatch($webpage);

        return $collection;
    }

    public function action(Webpage $webpage, Collection $collection): Collection
    {
        $this->asAction       = true;
        $this->initialisationFromShop($webpage->shop, []);

        return $this->handle($webpage, $collection);
    }

    public function asController(Webpage $webpage, Collection $collection): Collection
    {
        $this->initialisationFromShop($webpage->shop, []);

        return $this->handle($webpage, $collection);
    }
}
