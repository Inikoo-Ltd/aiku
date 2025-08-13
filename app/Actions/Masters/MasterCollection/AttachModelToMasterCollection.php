<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\Catalogue\Collection\Hydrators\MasterCollectionHydrateMasterProducts;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateFamilies;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateMasterCollections;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;

class AttachModelToMasterCollection extends GrpAction
{
    public function handle(MasterCollection $collection, MasterProductCategory|MasterAsset|MasterCollection $model): MasterCollection
    {

        if ($model instanceof Product) {
            $collection->masterProducts()->attach($model->id);
            MasterCollectionHydrateMasterProducts::dispatch($collection);
        } elseif ($model instanceof Collection) {
            $collection->masterCollections()->attach($model->id);
            MasterCollectionHydrateMasterCollections::dispatch($collection);
        } else {
            $collection->masterFamilies()->attach($model->id);
            MasterCollectionHydrateFamilies::dispatch($collection);
        }

        return $collection;
    }

    public function action(MasterCollection $masterCollection, MasterProductCategory|MasterAsset|MasterCollection $model): MasterCollection
    {

        $this->asAction = true;
        $this->initialisation($masterCollection->group, []);

        return $this->handle($masterCollection, $model);
    }
}
