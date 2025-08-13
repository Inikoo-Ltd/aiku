<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateMasterCollections;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterCollections;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;

class DetachMasterCollectionFromModel extends GrpAction
{
    public function handle(MasterShop|MasterProductCategory $parent, MasterCollection $masterCollection): MasterShop|MasterProductCategory
    {
        $oldParent = $masterCollection->parent;

        $parent->masterCollections()->detach($masterCollection);

        if ($parent instanceof MasterProductCategory) {
            $shop = $parent->shop;
            MasterProductCategoryHydrateMasterCollections::dispatch($parent);
        } else {
            $shop = $parent;
        }
        MasterShopHydrateMasterCollections::dispatch($shop);

        if ($oldParent instanceof MasterProductCategory) {
            MasterProductCategoryHydrateMasterCollections::dispatch($oldParent);
        }


        return $parent;
    }

    public function action(MasterShop|MasterProductCategory $parent, MasterCollection $masterCollection): MasterShop|MasterProductCategory
    {
        $this->asAction = true;
        $this->initialisation($parent->group, []);

        return $this->handle($parent, $masterCollection);
    }


    public function asController(MasterProductCategory $masterProductCategory, MasterCollection $masterCollection): MasterProductCategory
    {
        $this->initialisation($masterProductCategory->group, []);

        return $this->handle($masterProductCategory, $masterCollection);
    }

    public function htmlResponse(): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        return back();
    }
}
