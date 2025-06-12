<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AttachCollectionsToModel extends OrgAction
{
    public function handle(Collection $collection, array $modelData): Collection
    {
        // Attach departments
        foreach (Arr::get($modelData, 'departments', []) as $modelID) {
            if (!DB::table('model_has_collections')->where('collection_id', $collection->id)->where('model_type', 'ProductCategory')->where('model_id', $modelID)->exists()) {
                $department = ProductCategory::find($modelID);
                AttachCollectionToModel::make()->action($department, $collection);
            }
        }

        // Attach sub departments
        foreach (Arr::get($modelData, 'sub_departments', []) as $modelID) {
            if (!DB::table('model_has_collections')->where('collection_id', $collection->id)->where('model_type', 'ProductCategory')->where('model_id', $modelID)->exists()) {
                $subDepartment = ProductCategory::find($modelID);
                AttachCollectionToModel::make()->action($subDepartment, $collection);
            }
        }

        // Attach shops
        foreach (Arr::get($modelData, 'shops', []) as $modelID) {
            if (!DB::table('model_has_collections')->where('collection_id', $collection->id)->where('model_type', 'Shop')->where('model_id', $modelID)->exists()) {
                $shop = Shop::find($modelID);
                AttachCollectionToModel::make()->action($shop, $collection);
            }
        }


        return $collection;
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


    public function action(Collection $collection, $modelData): Collection
    {
        $this->asAction = true;
        $this->initialisationFromShop($collection->shop, $modelData);

        return $this->handle($collection, $modelData);
    }

    public function asController(Collection $collection, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($collection->shop, $request);

        return $this->handle($collection, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
