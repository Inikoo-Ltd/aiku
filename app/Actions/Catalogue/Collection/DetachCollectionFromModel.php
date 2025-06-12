<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;

class DetachCollectionFromModel extends OrgAction
{
    public function handle(Shop|ProductCategory $parent, Collection $collection): Shop|ProductCategory
    {

        $parent->collections()->detach($collection);
        return $parent;
    }

    public function action(Shop|ProductCategory $parent, Collection $collection): Shop|ProductCategory
    {
        if ($parent instanceof ProductCategory) {
            $shop = $parent->shop;
        } else {
            $shop = $parent;
        }

        $this->asAction = true;
        $this->initialisationFromShop($shop, []);

        return $this->handle($parent, $collection);
    }


    public function asController(ProductCategory $productCategory, Collection $collection): ProductCategory
    {
        $this->initialisationFromShop($productCategory->shop, []);

        return $this->handle($productCategory, $collection);
    }

    public function htmlResponse()
    {
        return back();
    }
}
