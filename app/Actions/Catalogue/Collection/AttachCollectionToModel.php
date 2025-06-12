<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;

class AttachCollectionToModel extends OrgAction
{
    public function handle(Shop|ProductCategory $parent, Collection $collection): Collection
    {
        if ($parent instanceof ProductCategory) {
            if ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                $parent->collections()->attach($collection->id, [
                    'type' => 'department',
                ]);
            }

            if ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $parent->collections()->attach($collection->id, [
                    'type' => 'sub_department',
                ]);
            }
        }
        if ($parent instanceof Shop) {
            $parent->collections()->attach($collection->id, [
                'type' => 'shop',
            ]);
        }


        return $collection;
    }

    public function htmlResponse()
    {
        return back();
    }

    public function action(Shop|ProductCategory $parent, Collection $collection): Collection
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

    public function asController(ProductCategory $productCategory, Collection $collection): Collection
    {
        $this->initialisationFromShop($productCategory->shop, []);

        return $this->handle($productCategory, $collection);
    }
}
