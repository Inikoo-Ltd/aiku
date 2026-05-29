<?php

/*
 * author Louis Perez
 * created on 29-05-2026-11h-01m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\RelatedChild\RelatedMasterProductCategories;

use App\Actions\OrgAction;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SyncMasterProductCategoryRelatedMasterProductCategories extends OrgAction
{
    private int $masterShopId;

    public function handle(MasterProductCategory $masterProductCategory, array $modelData): MasterProductCategory
    {
        $masterProductCategoryIds = array_unique(Arr::get($modelData, 'related_master_product_category_id', []));

        $relatedMasterProductCategories = [];
        $position = 0;
        foreach ($masterProductCategoryIds as $masterProductCategoryId) {
            $position++;
            $relatedMasterProductCategories[$masterProductCategoryId] = [
                'position' => $position
            ];
        }

        $masterProductCategory->relatedMasterProductCategories()->sync($relatedMasterProductCategories);

        SyncShopRelatedProductCategoriesFromMasterProductCategory::run($masterProductCategory);

        return $masterProductCategory;
    }

    public function rules(): array
    {
        return [
            'related_master_product_category_id' => ['sometimes', 'array'],
            'related_master_product_category_id.*' => ['integer', Rule::exists('master_product_categories', 'id')->where('master_shop_id', $this->masterShopId)],
        ];
    }

    public function action(MasterProductCategory $masterProductCategory, array $modelData): MasterProductCategory
    {
        $this->masterShopId = $masterProductCategory->master_shop_id;
        $this->asAction     = true;
        $this->initialisationFromGroup($masterProductCategory->group, $modelData);

        return $this->handle($masterProductCategory, $this->validatedData);
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): MasterProductCategory
    {
        $this->masterShopId = $masterProductCategory->master_shop_id;
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $this->validatedData);
    }
}
