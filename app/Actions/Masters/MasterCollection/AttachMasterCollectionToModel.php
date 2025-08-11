<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateCollections;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateMasterCollections;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;

class AttachMasterCollectionToModel extends GrpAction
{
    public function handle(MasterShop|MasterProductCategory $parent, MasterCollection $masterCollection): MasterCollection
    {
        if ($parent instanceof MasterProductCategory) {
            if ($parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                $parent->masterCollections()->attach($masterCollection->id, [
                    'type' => 'master_department',
                ]);
            }

            if ($parent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $parent->masterCollections()->attach($masterCollection->id, [
                    'type' => 'master_sub_department',
                ]);
            }

            MasterProductCategoryHydrateMasterCollections::dispatch($parent);
        }
        if ($parent instanceof MasterShop) {
            $parent->masterCollections()->attach($masterCollection->id, [
                'type' => 'master_shop',
            ]);
        }


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
