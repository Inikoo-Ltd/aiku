<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateCollections;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCollections;
use App\Actions\OrgAction;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;

class DetachCollectionFromModel extends OrgAction
{
    public function handle(Shop|ProductCategory $parent, Collection $collection): Shop|ProductCategory
    {
        $oldParent = $collection->parent;

        $parent->collections()->detach($collection);

        if ($parent instanceof ProductCategory) {
            $shop = $parent->shop;
            ProductCategoryHydrateCollections::dispatch($parent);
        } else {
            $shop = $parent;
        }
        ShopHydrateCollections::dispatch($shop);

        if ($oldParent instanceof ProductCategory) {
            ProductCategoryHydrateCollections::dispatch($oldParent);
        }


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

    public function htmlResponse(): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        return back();
    }
}
