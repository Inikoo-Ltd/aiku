<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\Catalogue\Collection\AttachModelToCollection;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateMasterFamilies;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateMasterCollections;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateMasterProducts;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;

class AttachModelToMasterCollection extends GrpAction
{
    public function handle(MasterCollection $masterCollection, MasterProductCategory|MasterAsset|MasterCollection $model, bool $attachChildren = true): MasterCollection
    {
        if ($model instanceof MasterAsset) {
            // Avoid attaching if already linked
            $alreadyAttached = $masterCollection->masterProducts()->where('master_assets.id', $model->id)->exists();
            if (!$alreadyAttached) {
                $masterCollection->masterProducts()->attach($model->id);
                if ($attachChildren) {
                    foreach ($masterCollection->childrenCollections as $collection) {
                        $shopModel = $model->products()->where('shop_id', $collection->shop_id)->first();
                        if ($shopModel) {
                            AttachModelToCollection::run($collection, $shopModel);
                        }
                    }
                }
            }
            MasterCollectionHydrateMasterProducts::dispatch($masterCollection);
        } elseif ($model instanceof MasterCollection) {
            // Avoid attaching if already linked
            $alreadyAttached = $masterCollection->masterCollections()->where('master_collections.id', $model->id)->exists();
            if (!$alreadyAttached) {
                $masterCollection->masterCollections()->attach($model->id);

                if ($attachChildren) {
                    foreach ($masterCollection->childrenCollections as $collection) {
                        $shopModel = $model->childrenCollections()->where('shop_id', $collection->shop_id)->first();
                        if ($shopModel) {
                            AttachModelToCollection::run($collection, $shopModel);
                        }
                    }
                }
            }
            MasterCollectionHydrateMasterCollections::dispatch($masterCollection);
        } else {
            // Avoid attaching if already linked
            $alreadyAttached = $masterCollection->masterFamilies()->where('master_product_categories.id', $model->id)->exists();
            if (!$alreadyAttached) {
                $masterCollection->masterFamilies()->attach($model->id);
                if ($attachChildren) {
                    foreach ($masterCollection->childrenCollections as $collection) {
                        $shopModel = $model->productCategories()->where('shop_id', $collection->shop_id)->first();
                        if ($shopModel) {
                            AttachModelToCollection::run($collection, $shopModel);
                        }
                    }
                }
            }
            MasterCollectionHydrateMasterFamilies::dispatch($masterCollection);
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
