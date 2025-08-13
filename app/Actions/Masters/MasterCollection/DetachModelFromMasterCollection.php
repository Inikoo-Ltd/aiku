<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateFamilies;
use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateProducts;
use App\Actions\Catalogue\Collection\Hydrators\MasterCollectionHydrateMasterProducts;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateFamilies;
use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class DetachModelFromMasterCollection extends GrpAction
{
    public function handle(MasterCollection $masterCollection, MasterAsset|MasterProductCategory $model): MasterCollection
    {
        if ($model instanceof MasterAsset) {
            $masterCollection->masterProducts()->detach($model->id);
            MasterCollectionHydrateMasterProducts::dispatch($masterCollection);
        } else {
            $masterCollection->masterFamilies()->detach($model->id);
            MasterCollectionHydrateFamilies::dispatch($masterCollection);
        }

        return $masterCollection;
    }

    public function rules(): array
    {
        return [
            'family'   => ['sometimes', Rule::exists('master_product_categories', 'id')->where('type', MasterProductCategoryTypeEnum::FAMILY)->where('group_id', $this->group->id)],
            'product' => ['sometimes', Rule::exists('master_assets', 'id')->where('group_id', $this->group->id)],
        ];
    }


    public function action(MasterCollection $masterCollection, MasterAsset|MasterProductCategory $model): MasterCollection
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
        }

        return $this->handle($masterCollection, $model);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
