<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\GrpAction;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;

class StoreCollectionsFromMasterCollection extends GrpAction
{
    public function handle(MasterShop|MasterProductCategory $parent, MasterCollection $masterCollection)
    {
        $data = [
            'code' => $masterCollection->code,
            'name' => $masterCollection->name,
            'description' => $masterCollection->description,
            'master_collection_id' => $masterCollection->id
        ];

        if ($masterCollection->image_id) {
            data_set($data, 'image_id', $masterCollection->image_id);
        }

        if ($parent instanceof MasterShop) {
            foreach ($parent->shops->where('state', ShopStateEnum::OPEN) as $shop) {
                StoreCollection::make()->action($shop, $data);
            }
        } else {
            foreach ($parent->productCategories as $productCategory) {
                StoreCollection::make()->action($productCategory, $data);
            }
        }

    }

    public function action(MasterShop|MasterProductCategory $parent, MasterCollection $masterCollection)
    {
        $this->initialisation($parent->group, []);

        return $this->handle($parent, $masterCollection);
    }
}
