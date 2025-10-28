<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\Catalogue\Collection\DetachModelFromCollection;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateMasterFamilies;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateMasterCollections;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateMasterProducts;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class DetachMasterModelFromMasterCollection extends GrpAction
{
    public function handle(MasterCollection $masterCollection, MasterAsset|MasterProductCategory|MasterCollection $model, bool $detachChildren = true): MasterCollection
    {
        if ($model instanceof MasterAsset) {
            $masterCollection->masterProducts()->detach($model->id);
            MasterCollectionHydrateMasterProducts::dispatch($masterCollection);
            if ($detachChildren) {
                foreach ($masterCollection->childrenCollections as $collection) {
                    $shopModel = $model->products()->where('shop_id', $collection->shop_id)->first();
                    if ($shopModel) {
                        DetachModelFromCollection::run($collection, $shopModel);
                    }
                }
            }
        } elseif ($model instanceof MasterCollection) {
            $masterCollection->masterCollections()->detach($model->id);

            if ($detachChildren) {
                foreach ($masterCollection->childrenCollections as $collection) {
                    $shopModel = $model->childrenCollections()->where('shop_id', $collection->shop_id)->first();
                    if ($shopModel) {
                        DetachModelFromCollection::run($collection, $shopModel);
                    }
                }
            }

            MasterCollectionHydrateMasterCollections::dispatch($masterCollection);
        } else {
            $masterCollection->masterFamilies()->detach($model->id);

            if ($detachChildren) {
                foreach ($masterCollection->childrenCollections as $collection) {
                    $shopModel = $model->productCategories()->where('shop_id', $collection->shop_id)->first();
                    if ($shopModel) {
                        DetachModelFromCollection::run($collection, $shopModel);
                    }
                }
            }
            MasterCollectionHydrateMasterFamilies::dispatch($masterCollection);
        }

        return $masterCollection;
    }

    public function rules(): array
    {
        return [
            'family'     => ['sometimes', Rule::exists('master_product_categories', 'id')->where('type', MasterProductCategoryTypeEnum::FAMILY)->where('group_id', $this->group->id)],
            'product'    => ['sometimes', Rule::exists('master_assets', 'id')->where('group_id', $this->group->id)],
            'collection' => ['sometimes', Rule::exists('master_collections', 'id')->where('group_id', $this->group->id)],
        ];
    }


    public function action(MasterCollection $masterCollection, MasterAsset|MasterProductCategory|MasterCollection $model): MasterCollection
    {
        $this->asAction = true;
        $this->initialisation($masterCollection->group, []);

        return $this->handle($masterCollection, $model);
    }

    public function asController(MasterCollection $masterCollection, ActionRequest $request): MasterCollection
    {
        $this->initialisation($masterCollection->group, $request);

        $modelData = $this->validatedData;
        $model     = null;

        if (Arr::has($modelData, 'family')) {
            $model = MasterProductCategory::findOrFail(Arr::get($modelData, 'family'));
        } elseif (Arr::has($modelData, 'product')) {
            $model = MasterAsset::findOrFail(Arr::get($modelData, 'product'));
        } elseif (Arr::has($modelData, 'collection')) {
            $model = MasterCollection::findOrFail(Arr::get($modelData, 'collection'));
        }

        return $this->handle($masterCollection, $model);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
