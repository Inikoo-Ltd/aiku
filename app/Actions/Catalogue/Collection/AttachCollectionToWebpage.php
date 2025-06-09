<?php
/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateItems;
use App\Actions\OrgAction;
use App\Models\Catalogue\Collection;
use App\Models\Web\Webpage;

class AttachCollectionToWebpage extends OrgAction
{
    public function handle(Webpage $webpage, Collection $collection): Collection
    {
        $webpage->webpageHasCollections()->create([
            'collection_id' => $collection->id,
        ]);
        CollectionHydrateItems::dispatch($collection);
        return $collection;
    }

    public function action(Webpage $webpage, Collection $collection): Collection
    {
        $this->asAction       = true;
        $this->initialisationFromShop($webpage->shop, []);

        return $this->handle($webpage, $collection);
    }
}
