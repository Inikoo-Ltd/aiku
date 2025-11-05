<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\Catalogue\Collection\AttachCollectionToModel;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateParents;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateMasterCollections;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;

class AttachMasterCollectionToModel extends GrpAction
{
    public function handle(MasterShop|MasterProductCategory $parent, MasterCollection $masterCollection, bool $attachChildren = true): MasterCollection
    {
        if ($parent instanceof MasterProductCategory) {
            // Avoid attaching if already linked
            $alreadyAttached = $parent->masterCollections()->where('master_collections.id', $masterCollection->id)->exists();

            if (!$alreadyAttached && $parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                $parent->masterCollections()->attach($masterCollection->id, [
                    'type' => 'master_department',
                ]);

                if ($attachChildren) {
                    foreach ($masterCollection->childrenCollections as $collection) {
                        $shopModel = $parent->productCategories()->where('type', ProductCategoryTypeEnum::DEPARTMENT)->where('shop_id', $collection->shop_id)->first();
                        if ($shopModel) {
                            AttachCollectionToModel::run($shopModel, $collection);
                        }
                    }
                }
            }

            if (!$alreadyAttached && $parent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $parent->masterCollections()->attach($masterCollection->id, [
                    'type' => 'master_sub_department',
                ]);

                if ($attachChildren) {
                    foreach ($masterCollection->childrenCollections as $collection) {
                        $shopModel = $parent->productCategories()->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)->where('shop_id', $collection->shop_id)->first();
                        if ($shopModel) {
                            AttachCollectionToModel::run($shopModel, $collection);
                        }
                    }
                }
            }

            MasterProductCategoryHydrateMasterCollections::dispatch($parent);
        }
        if ($parent instanceof MasterShop) {
            // Avoid attaching if already linked
            $alreadyAttached = $parent->masterCollections()->where('master_collections.id', $masterCollection->id)->exists();

            if (!$alreadyAttached) {
                $parent->masterCollections()->attach($masterCollection->id, [
                    'type' => 'master_shop',
                ]);

                if ($attachChildren) {
                    foreach ($masterCollection->childrenCollections as $collection) {
                        AttachCollectionToModel::run($collection->shop, $collection);
                    }
                }
            }
        }


        MasterCollectionHydrateParents::run($masterCollection);

        return $masterCollection;
    }

    public function htmlResponse()
    {
        return back();
    }

    public function action(MasterShop|MasterProductCategory $parent, MasterCollection $collection): MasterCollection
    {
        $this->asAction = true;
        $this->initialisation($parent->group, []);

        return $this->handle($parent, $collection);
    }

    public function asController(MasterProductCategory $masterProductCategory, MasterCollection $masterCollection): MasterCollection
    {
        $this->initialisation($masterProductCategory->group, []);

        return $this->handle($masterProductCategory, $masterCollection);
    }
}
