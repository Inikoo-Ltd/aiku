<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterCollection;

use App\Actions\GrpAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AttachMultipleParentsToAMasterCollection extends GrpAction
{
    public function handle(MasterCollection $masterCollection, array $modelData): MasterCollection
    {
        // Attach departments
        foreach (Arr::get($modelData, 'departments', []) as $modelID) {
            if (!DB::table('model_has_master_collections')->where('master_collection_id', $masterCollection->id)->where('model_type', 'MasterProductCategory')->where('model_id', $modelID)->exists()) {
                $department = MasterProductCategory::find($modelID);
                AttachMasterCollectionToModel::make()->action($department, $masterCollection);
            }
        }

        // Attach sub departments
        foreach (Arr::get($modelData, 'sub_departments', []) as $modelID) {
            if (!DB::table('model_has_master_collections')->where('master_collection_id', $masterCollection->id)->where('model_type', 'MasterProductCategory')->where('model_id', $modelID)->exists()) {
                $subDepartment = MasterProductCategory::find($modelID);
                AttachMasterCollectionToModel::make()->action($subDepartment, $masterCollection);
            }
        }

        // Attach shops
        foreach (Arr::get($modelData, 'shops', []) as $modelID) {
            if (!DB::table('model_has_master_collections')->where('master_collection_id', $masterCollection->id)->where('model_type', 'Shop')->where('model_id', $modelID)->exists()) {
                $shop = Shop::find($modelID);
                AttachMasterCollectionToModel::make()->action($shop, $masterCollection);
            }
        }


        return $masterCollection;
    }

    public function rules(): array
    {
        return [
            'departments'       => ['nullable', 'array'],
            'departments.*'     => [Rule::exists('product_categories', 'id')->where('type', ProductCategoryTypeEnum::DEPARTMENT)->where('shop_id', $this->shop->id)],
            'sub_departments'   => ['nullable', 'array'],
            'sub_departments.*' => [Rule::exists('product_categories', 'id')->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)->where('shop_id', $this->shop->id)],
            'shops'             => ['nullable', 'array'],
            'shops.*'           => [Rule::exists('shops', 'id')],
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
