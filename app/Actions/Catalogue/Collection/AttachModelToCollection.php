<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateFamilies;
use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateProducts;
use App\Actions\OrgAction;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;

class AttachModelToCollection extends OrgAction
{
    public function handle(Collection $collection, ProductCategory|Product $model): Collection
    {

        if ($model instanceof Product) {
            $collection->products()->attach($model->id);
            CollectionHydrateProducts::dispatch($collection);
        } else {
            $collection->families()->attach($model->id);
            CollectionHydrateFamilies::dispatch($collection);
        }

        SyncIndirectProductsToCollection::dispatch($collection);

        return $collection;
    }

    public function action(Collection $collection, ProductCategory|Product $model): Collection
    {

        $this->asAction = true;
        $this->initialisationFromShop($collection->shop, []);

        return $this->handle($collection, $model);
    }
}
