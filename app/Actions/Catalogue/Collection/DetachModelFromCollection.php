<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateItems;
use App\Actions\OrgAction;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\ActionRequest;

class DetachModelFromCollection extends OrgAction
{
    public function handle(Collection $collection, Product|ProductCategory $model): Collection
    {


        if ($model instanceof Product) {
            $collection->products()->detach($model->id);
        } else {
            $collection->families()->detach($model->id);
        }

        CollectionHydrateItems::dispatch($collection);
        return $collection;
    }



    public function action(Collection $collection, Product|ProductCategory $model): Collection
    {
        $this->asAction       = true;
        $this->initialisationFromShop($collection->shop, []);

        return $this->handle($collection, $model);
    }

    public function asController(Collection $collection, Product|ProductCategory $model, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($collection->shop, $request);
        return $this->handle($collection, $model);
    }
}
