<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateFamilies;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateMasterCollections;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateMasterProducts;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;

class AttachModelToMasterCollection extends GrpAction
{
    public function handle(MasterCollection $masterCollection, MasterProductCategory|MasterAsset|MasterCollection $model): MasterCollection
    {
        if ($model instanceof MasterAsset) {
            // Avoid attaching if already linked
            $alreadyAttached = $masterCollection->masterProducts()->where('master_assets.id', $model->id)->exists();
            if (!$alreadyAttached) {
                $masterCollection->masterProducts()->attach($model->id);
            }
            MasterCollectionHydrateMasterProducts::dispatch($masterCollection);
        } elseif ($model instanceof MasterCollection) {
            // Avoid attaching if already linked
            $alreadyAttached = $masterCollection->masterCollections()->where('master_collections.id', $model->id)->exists();
            if (!$alreadyAttached) {
                $masterCollection->masterCollections()->attach($model->id);
            }
            MasterCollectionHydrateMasterCollections::dispatch($masterCollection);
        } else {
            // Avoid attaching if already linked
            $alreadyAttached = $masterCollection->masterFamilies()->where('master_product_categories.id', $model->id)->exists();
            if (!$alreadyAttached) {
                $masterCollection->masterFamilies()->attach($model->id);
            }
            MasterCollectionHydrateFamilies::dispatch($masterCollection);
        }

        return $masterCollection;
    }

    public function action(MasterCollection $masterCollection, MasterProductCategory|MasterAsset|MasterCollection $model): MasterCollection
    {

        $this->asAction = true;
        $this->initialisation($masterCollection->group, []);

        return $this->handle($masterCollection, $model);
    }
}
