<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\AttachModelToMasterCollection;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AttachModelsToMasterCollection extends GrpAction
{
    public function handle(MasterCollection $masterCollection, array $modelData): MasterCollection
    {
        foreach (Arr::get($modelData, 'families', []) as $modelID) {
            if (!DB::table('master_collection_has_models')->where('master_collection_id', $masterCollection->id)->where('model_type', 'MasterProductCategory')->where('model_id', $modelID)->exists()) {
                $family = MasterProductCategory::find($modelID);
                AttachModelToMasterCollection::make()->action($masterCollection, $family);
            }
        }

        foreach (Arr::get($modelData, 'products', []) as $modelID) {
            if (!DB::table('master_collection_has_models')->where('master_collection_id', $masterCollection->id)->where('model_type', 'MasterAsset')->where('model_id', $modelID)->exists()) {
                $product = MasterAsset::find($modelID);
                AttachModelToMasterCollection::make()->action($masterCollection, $product);
            }
        }

        foreach (Arr::get($modelData, 'collections', []) as $modelID) {
            if (!DB::table('master_collection_has_models')->where('master_collection_id', $masterCollection->id)->where('model_type', 'MasterCollection')->where('model_id', $modelID)->exists()) {
                $collectionToAttach = MasterCollection::find($modelID);
                AttachModelToMasterCollection::make()->action($masterCollection, $collectionToAttach);
            }
        }


        return $masterCollection;
    }

    public function rules(): array
    {
        return [
            'families'   => ['nullable', 'array'],
            'families.*' => [Rule::exists('master_product_categories', 'id')->where('type', MasterProductCategoryTypeEnum::FAMILY)->where('group_id', $this->group->id)],
            'products'   => ['nullable', 'array'],
            'products.*' => [Rule::exists('master_assets', 'id')->where('group_id', $this->group->id)],
            'collections'   => ['nullable', 'array'],
            'collections.*' => [Rule::exists('master_collections', 'id')->where('group_id', $this->group->id)],
        ];
    }

    public function action(MasterCollection $masterCollection, $modelData): MasterCollection
    {
        $this->asAction = true;
        $this->initialisation($masterCollection->group, $modelData);

        return $this->handle($masterCollection, $modelData);
    }

    public function asController(MasterCollection $masterCollection, ActionRequest $request): MasterCollection
    {
        $this->initialisation($masterCollection->group, $request);

        return $this->handle($masterCollection, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
